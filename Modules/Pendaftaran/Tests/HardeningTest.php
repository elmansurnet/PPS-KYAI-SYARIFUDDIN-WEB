<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Pendaftaran\Http\Requests\KirimRequest;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Rules\KelasForJenjang;
use Modules\Pendaftaran\Rules\PlainText;
use Modules\Pendaftaran\Http\Requests\AkademikRequest;
use Modules\Pendaftaran\Support\KkFileInspector;

uses(RefreshDatabase::class);

function hardPdf(string $objects = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog {$objects}>>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

test('clean minimal pdf is accepted by the inspector', function () {
    expect(KkFileInspector::detect(hardPdf()))->toBe('application/pdf');
});

test('pdf with active content tokens is rejected', function (string $token) {
    expect(KkFileInspector::detect(hardPdf("{$token} 2 0 R ")))->toBeNull();
})->with(['/JavaScript', '/JS', '/Launch', '/OpenAction', '/AA', '/EmbeddedFile', '/RichMedia', '/XFA']);

test('pdf token names that merely start with a token are still rejected, other names pass', function () {
    // /Type, /Pages, /Author dan sejenisnya bukan token aktif.
    expect(KkFileInspector::detect(hardPdf('/Pages 3 0 R /Author (x) /Title (Kartu) ')))->toBe('application/pdf');
});

test('plain text rejects unicode format characters', function (string $char) {
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
    'zwsp U+200B' => "\u{200B}",
    'zwnj U+200C' => "\u{200C}",
    'zwj U+200D' => "\u{200D}",
    'bom U+FEFF' => "\u{FEFF}",
]);

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
