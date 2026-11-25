<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Pendaftaran\Http\Requests\KirimRequest;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Rules\KelasForJenjang;
use Modules\Pendaftaran\Rules\PlainText;
use Modules\Pendaftaran\Http\Requests\AkademikRequest;
use Modules\Pendaftaran\Support\FormInput;
use Modules\Pendaftaran\Support\KkFileInspector;

uses(RefreshDatabase::class);

function hardPdf(string $objects = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog {$objects}>>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

test('clean minimal pdf is accepted by the inspector', function () {
    expect(KkFileInspector::detect(hardPdf()))->toBe('application/pdf');
});

test('pdf with active content names is rejected', function (string $objects) {
    expect(KkFileInspector::detect(hardPdf($objects)))->toBeNull();
})->with([
    '/JavaScript' => '/S /JavaScript ',
    '/JS string' => '/JS (app.alert(1)) ',
    '/JS hex' => '/JS <6170702E616C657274> ',
    '/Launch' => '/S /Launch ',
    '/OpenAction' => '/OpenAction 2 0 R ',
    '/AA dict' => '/AA << /O 2 0 R >> ',
    '/EmbeddedFile' => '/Type /EmbeddedFile ',
    '/EmbeddedFiles tree' => '/Names << /EmbeddedFiles 3 0 R >> ',
    '/RichMedia' => '/Subtype /RichMedia ',
    '/XFA' => '/XFA 4 0 R ',
]);

test('pdf names that merely start with a token or look similar are accepted', function (string $objects) {
    expect(KkFileInspector::detect(hardPdf($objects)))->toBe('application/pdf');
})->with([
    'subset font' => '/BaseFont /AAAAAA+ArialMT ',
    'quartz keywords' => '/AAPL:Keywords [] ',
    '/JSON' => '/JSON 1 ',
    'ordinary names' => '/Pages 3 0 R /Author (x) /Title (Kartu) ',
]);

test('random binary inside a stream does not trigger the active content scan', function () {
    $binary = random_bytes(4096)."/JS (x) /AA /JavaScript /OpenAction /Launch ".random_bytes(4096);
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length ".strlen($binary)." >>\nstream\n".$binary."\nendstream\nendobj\n2 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 2 0 R >>\n%%EOF\n";

    // Heuristik <script/<?php di dalam biner acak bisa kebetulan muncul; buang kemungkinannya agar tes deterministik.
    expect(preg_match('/<\?php|<script|<html|<!doctype\s+html/i', $pdf))->toBe(0)
        ->and(KkFileInspector::detect($pdf))->toBe('application/pdf');
});

test('active content outside the stream is still rejected when a stream is present', function () {
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length 3 >>\nstream\nabc\nendstream\nendobj\n2 0 obj\n<< /OpenAction << /S /JavaScript /JS (x) >> >>\nendobj\ntrailer\n<< /Root 2 0 R >>\n%%EOF\n";

    expect(KkFileInspector::detect($pdf))->toBeNull();
});

test('plain text rejects bidi and control characters', function (string $char) {
    $errors = [];
    (new PlainText)->validate('nama', "Ahmad{$char}Fulan", function (string $m) use (&$errors) {
        $errors[] = $m;
    });

    expect($errors)->not->toBeEmpty();
})->with([
    'rlo U+202E' => "\u{202E}",
    'lre U+202A' => "\u{202A}",
    'pdf U+202C' => "\u{202C}",
    'isolate U+2066' => "\u{2066}",
    'pdi U+2069' => "\u{2069}",
    'lrm U+200E' => "\u{200E}",
    'rlm U+200F' => "\u{200F}",
    'control bell' => "\x07",
]);

test('form input strips invisible non-bidi characters pasted from other apps', function (string $char) {
    expect(FormInput::text("Ahmad{$char}Fulan"))->toBe('AhmadFulan')
        ->and(FormInput::name("Sa{$char}'id"))->toBe("Sa'id");
})->with([
    'zwsp' => "\u{200B}", 'zwnj' => "\u{200C}", 'zwj' => "\u{200D}",
    'soft hyphen' => "\u{00AD}", 'bom' => "\u{FEFF}", 'word joiner' => "\u{2060}",
]);

test('form input does not hide bidi overrides, so validation still rejects them', function () {
    $text = FormInput::text("Ahmad\u{202E}Fulan");
    $errors = [];
    (new PlainText)->validate('nama', $text, function (string $m) use (&$errors) {
        $errors[] = $m;
    });

    expect($errors)->not->toBeEmpty();
});
test('plain text still accepts normal and arabic text', function () {
    $errors = [];
    foreach (['Ahmad Fulan', "Sa'id bin Abdullah", 'عبد الله', 'Jl. Kyai No. 5/7'] as $v) {
        (new PlainText)->validate('nama', $v, function (string $m) use (&$errors) {
            $errors[] = $m;
        });
    }

    expect($errors)->toBeEmpty();
});

test('kelas rule matches case-insensitively and returns the canonical name', function () {
    $smp = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    KelasOption::create(['jenjang_id' => $smp->id, 'name' => 'Kelas 7', 'sort_order' => 1, 'is_active' => true]);

    expect(KelasForJenjang::canonical($smp->id, 'kelas 7'))->toBe('Kelas 7')
        ->and(KelasForJenjang::canonical($smp->id, 'KELAS 7'))->toBe('Kelas 7')
        ->and(KelasForJenjang::canonical($smp->id, 'Kelas 8'))->toBeNull();

    $failed = false;
    (new KelasForJenjang)->setData(['jenjang_id' => $smp->id])
        ->validate('kelas', 'kelas 7', function () use (&$failed) {
            $failed = true;
        });
    expect($failed)->toBeFalse();

    $attributes = AkademikRequest::toAttributes([
        'pernah_mondok' => 'tidak', 'pendidikan_terakhir_id' => '1', 'jenjang_id' => (string) $smp->id, 'kelas' => 'kelas 7',
    ]);
    expect($attributes['kelas'])->toBe('Kelas 7');
});

test('turnstile availability needs both keys', function () {
    Log::spy();

    config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
    expect(KirimRequest::turnstileAvailable())->toBeTrue();

    config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => null]);
    expect(KirimRequest::turnstileAvailable())->toBeFalse();

    config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => 'secret']);
    expect(KirimRequest::turnstileAvailable())->toBeFalse();
});

test('turnstile misconfiguration is logged once, not on every call', function () {
    config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
    Log::shouldReceive('error')->once();

    foreach (range(1, 5) as $i) {
        expect(KirimRequest::turnstileAvailable())->toBeFalse();
    }
});
