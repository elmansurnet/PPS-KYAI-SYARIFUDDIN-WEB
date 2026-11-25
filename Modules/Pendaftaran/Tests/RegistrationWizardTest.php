<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Settings\SiteSettings;
use Modules\Core\Support\Navigation;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;
use Modules\Pendaftaran\Services\KkStorage;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Finder\SplFileInfo;

uses(RefreshDatabase::class);

const WIZ_NIK = '3201010101010001';
const WIZ_NAMA = 'Ahmad Fauzi';
const WIZ_SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

beforeEach(function () {
    $this->withoutVite();

    // Disk KK asli (driver privat tanpa URL) di folder sementara, bukan Storage::fake, agar perilaku disk ikut teruji.
    $this->kkRoot = storage_path('framework/testing/kk-'.Str::random(10));
    config(['filesystems.disks.kk.root' => $this->kkRoot]);
    Storage::forgetDisk('kk');

    $this->master = wizMaster();
    wizSettings(['whatsapp_number' => '0812-1111-2222']);
});

afterEach(function () {
    File::deleteDirectory($this->kkRoot);
});

/** @return array{smp: Jenjang, sma: Jenjang, pend: PendidikanTerakhir} */
function wizMaster(): array
{
    $smp = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    $sma = Jenjang::create(['name' => 'SMA/MA', 'sort_order' => 2, 'is_active' => true]);
    KelasOption::create(['jenjang_id' => $smp->id, 'name' => 'Kelas 7', 'sort_order' => 1, 'is_active' => true]);
    KelasOption::create(['jenjang_id' => $sma->id, 'name' => 'Kelas 10', 'sort_order' => 1, 'is_active' => true]);
    $pend = PendidikanTerakhir::create(['name' => 'SD/MI', 'sort_order' => 1, 'is_active' => true]);

    return compact('smp', 'sma', 'pend');
}

function wizSettings(array $values): void
{
    $settings = app(SiteSettings::class);
    foreach ($values as $key => $value) {
        $settings->{$key} = $value;
    }
    $settings->save();
}

function wizSantri(array $override = []): array
{
    return $override + [
        'nik' => WIZ_NIK,
        'nama_lengkap' => WIZ_NAMA,
        'tempat_lahir' => 'Bogor',
        'tanggal_lahir' => now()->subYears(12)->format('Y-m-d'),
        'jalan_dusun' => 'Dusun Krajan',
        'rt' => '1',
        'rw' => '02',
        'desa' => 'Sukamaju',
        'kecamatan' => 'Cibinong',
        'kabupaten' => 'Bogor',
        'provinsi' => 'Jawa Barat',
    ];
}

function wizAkademik(array $master, array $override = []): array
{
    return $override + [
        'pernah_mondok' => 'tidak',
        'pendidikan_terakhir_id' => $master['pend']->id,
        'jenjang_id' => $master['smp']->id,
        'kelas' => 'Kelas 7',
    ];
}

function wizOrtu(array $override = []): array
{
    return $override + [
        'orang_tua_meninggal' => 'tidak',
        'ayah_nama' => 'Budi Santoso',
        'ayah_pekerjaan' => 'Petani',
        'ayah_hp' => '0812-3456-7890',
        'ibu_nama' => 'Siti Aminah',
        'ibu_pekerjaan' => 'Ibu rumah tangga',
        'ibu_hp' => '',
        'alamat_sama' => '1',
    ];
}

function wizWali(): array
{
    return [
        'wali_nama' => 'Hasan Basri',
        'wali_pekerjaan' => 'Pedagang',
        'wali_hp' => '+62 813 9999 8888',
        'wali_jalan_dusun' => 'Jl. Melati 5',
        'wali_rt' => '3',
        'wali_rw' => '4',
        'wali_desa' => 'Cikaret',
        'wali_kecamatan' => 'Cibinong',
        'wali_kabupaten' => 'Bogor',
        'wali_provinsi' => 'Jawa Barat',
    ];
}

function wizJpegBytes(): string
{
    $img = imagecreatetruecolor(24, 16);
    imagefilledrectangle($img, 0, 0, 23, 15, imagecolorallocate($img, 40, 120, 70));
    ob_start();
    imagejpeg($img, null, 80);

    return (string) ob_get_clean();
}

function wizPdfBytes(int $padTo = 0): string
{
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
    if ($padTo > 0) {
        $pdf .= str_repeat("% padding padding padding padding\n", (int) ceil($padTo / 34));
    }

    return $pdf."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function wizKk(string $name = 'kk.jpg', ?string $bytes = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes ?? wizJpegBytes());
}

/** @return array<int, SplFileInfo> */
function wizKkFiles(string $root): array
{
    return File::isDirectory($root) ? File::allFiles($root) : [];
}

function wizTurnstile(bool $success = true): void
{
    Http::fake([WIZ_SITEVERIFY => Http::response(['success' => $success])]);
}

/** Isi langkah 1 sampai 3 dengan data sah (opsional ditimpa). */
function wizFillSteps($test, array $master, array $santri = [], array $akademik = [], array $ortu = []): void
{
    $test->post(route('daftar.simpan', 1), wizSantri($santri))->assertRedirect(route('daftar.langkah', 2));
    $test->post(route('daftar.simpan', 2), wizAkademik($master, $akademik))->assertRedirect(route('daftar.langkah', 3));
    $test->post(route('daftar.simpan', 3), wizOrtu($ortu))->assertRedirect(route('daftar.langkah', 4));
}

function wizSubmit($test, ?UploadedFile $kk = null, string $token = 'tok-ok')
{
    return $test->post(route('daftar.kirim'), [
        'kk' => $kk ?? wizKk(),
        'cf-turnstile-response' => $token,
    ]);
}

// ---------------------------------------------------------------------------------------------
// Tes dari brief
// ---------------------------------------------------------------------------------------------

test('test_valid_submission_saves_applicant_and_redirects_to_whatsapp', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    $response = wizSubmit($this);

    $response->assertStatus(303)->assertRedirect(route('daftar.selesai'));
    expect(Applicant::count())->toBe(1);

    $applicant = Applicant::first();
    expect($applicant->nik)->toBe(WIZ_NIK)
        ->and($applicant->nama_lengkap)->toBe(WIZ_NAMA)
        ->and($applicant->rt)->toBe('001')
        ->and($applicant->rw)->toBe('002')
        ->and($applicant->jenjang_id)->toBe($this->master['smp']->id)
        ->and($applicant->kelas)->toBe('Kelas 7')
        ->and($applicant->ayah_hp)->toBe('081234567890')
        ->and($applicant->ibu_hp)->toBeNull()
        ->and($applicant->pernah_mondok)->toBeFalse()
        ->and($applicant->orang_tua_meninggal)->toBeFalse()
        ->and($applicant->kk_path)->not->toBeEmpty()
        ->and($applicant->sudah_dihubungi)->toBeFalse()
        ->and($applicant->sudah_bayar)->toBeFalse()
        ->and($applicant->sudah_masuk)->toBeFalse();

    Http::assertSent(fn ($r) => $r->url() === WIZ_SITEVERIFY && $r['response'] === 'tok-ok');

    $message = "Assalamu'alaikum, saya sudah mendaftarkan ".WIZ_NAMA.' (NIK ...0001) melalui website. Mohon ditindaklanjuti.';
    $waUrl = 'https://wa.me/6281211112222?text='.rawurlencode($message);

    $page = $this->get(route('daftar.selesai'));
    $page->assertOk()
        ->assertSee(WIZ_NAMA)
        ->assertSee('Lanjut ke WhatsApp')
        ->assertSee('href="'.e($waUrl).'"', false)
        ->assertSee('<meta http-equiv="refresh" content="5;url='.e($waUrl).'">', false)
        ->assertDontSee(WIZ_NIK);

    // Audit tanpa data pribadi: hanya id pendaftar.
    $activity = Activity::where('event', 'pendaftaran.baru')->sole();
    expect($activity->subject_id)->toBe($applicant->id)
        ->and(json_encode($activity->properties))->not->toContain(WIZ_NAMA)->not->toContain(WIZ_NIK);

    // Draf dibersihkan: langkah 2 tidak bisa dibuka lagi.
    $this->get(route('daftar.langkah', 2))->assertRedirect(route('daftar'));
});

test('test_nik_must_be_exactly_16_digits', function (string $nik) {
    $this->post(route('daftar.simpan', 1), wizSantri(['nik' => $nik]))
        ->assertRedirect(route('daftar'))
        ->assertSessionHasErrors(['nik' => 'NIK harus tepat 16 digit angka sesuai Kartu Keluarga.']);
})->with([
    '15 digit' => ['320101010101000'],
    '17 digit' => ['32010101010100011'],
    'huruf' => ['320101010101000A'],
    'kosong' => [''],
]);

test('nik with spaces, dots or dashes is normalised and accepted', function () {
    $this->post(route('daftar.simpan', 1), wizSantri(['nik' => '3201 0101 0101 0001']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('daftar.langkah', 2));

    $this->post(route('daftar.simpan', 1), wizSantri(['nik' => '3201.0101-0101.0001']))
        ->assertSessionHasNoErrors();
});

test('nik error message never echoes the submitted value', function () {
    $this->post(route('daftar.simpan', 1), wizSantri(['nik' => '987654321']));

    $html = $this->get(route('daftar'))->getContent();
    expect($html)->toContain('NIK harus tepat 16 digit angka sesuai Kartu Keluarga.');
    // Nilai hanya muncul sebagai isian ulang di input NIK, tidak di pesan/ringkasan galat.
    expect(substr_count($html, '987654321'))->toBe(1)->and($html)->toContain('value="987654321"');
});

test('test_duplicate_nik_shows_clear_message_and_creates_no_second_record', function () {
    wizTurnstile();
    $existing = Applicant::factory()->create(['nik' => WIZ_NIK, 'nama_lengkap' => 'Nama Rahasia Lama']);

    wizFillSteps($this, $this->master);
    $response = wizSubmit($this);

    $response->assertRedirect(route('daftar.langkah', 4));
    expect(Applicant::count())->toBe(1);
    expect(wizKkFiles($this->kkRoot))->toBeEmpty();

    $html = $this->get(route('daftar.langkah', 4))->getContent();
    expect($html)->toContain('Data dengan NIK ini sudah kami terima. Silakan hubungi panitia melalui WhatsApp bila perlu bantuan.')
        ->not->toContain('Nama Rahasia Lama');
});

test('test_double_submit_creates_one_record', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this)->assertRedirect(route('daftar.selesai'));
    // Klik kedua (draf sudah dibersihkan, flash terima kasih masih ada): diarahkan ke halaman terima kasih.
    wizSubmit($this)->assertRedirect(route('daftar.selesai'));

    expect(Applicant::count())->toBe(1)
        ->and(wizKkFiles($this->kkRoot))->toHaveCount(1);
    $this->get(route('daftar.selesai'))->assertOk()->assertSee(WIZ_NAMA);
});

test('double submit racing with the draft still present yields one record and the friendly message', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);
    $draft = session('pendaftaran.draft');

    wizSubmit($this)->assertRedirect(route('daftar.selesai'));

    // Permintaan kedua yang membaca sesi sebelum permintaan pertama selesai: draf masih ada.
    session(['pendaftaran.draft' => $draft]);
    session()->forget('pendaftaran.selesai');
    wizSubmit($this)->assertRedirect(route('daftar.langkah', 4));

    expect(Applicant::count())->toBe(1);
    $this->get(route('daftar.langkah', 4))->assertSee('Data dengan NIK ini sudah kami terima.');
});

test('unique violation from a concurrent insert is caught without leaking data or orphan files', function () {
    wizTurnstile();
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs) {
        $logs[] = $e->message.' '.json_encode($e->context);
    });

    wizFillSteps($this, $this->master);

    // Simulasikan permintaan lain yang menyisipkan NIK yang sama tepat setelah pengecekan awal.
    $inserted = false;
    Applicant::creating(function (Applicant $a) use (&$inserted) {
        if (! $inserted) {
            $inserted = true;
            Applicant::factory()->create(['nik' => WIZ_NIK]);
        }
    });

    wizSubmit($this)->assertRedirect(route('daftar.langkah', 4));

    expect(Applicant::where('nama_lengkap', WIZ_NAMA)->count())->toBe(0)
        ->and(wizKkFiles($this->kkRoot))->toBeEmpty();
    $this->get(route('daftar.langkah', 4))->assertSee('Data dengan NIK ini sudah kami terima.');

    $all = implode("\n", $logs);
    expect($all)->not->toContain(WIZ_NIK)->not->toContain(WIZ_NAMA)->not->toContain('Budi Santoso')->not->toContain('insert into');
});

test('test_pernah_mondok_requires_previous_pondok_fields', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());

    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['pernah_mondok' => 'ya']))
        ->assertRedirect(route('daftar.langkah', 2))
        ->assertSessionHasErrors([
            'pondok_sebelumnya' => 'Nama pondok sebelumnya wajib diisi.',
            'pendidikan_pondok_sebelumnya' => 'Pendidikan di pondok sebelumnya wajib diisi.',
        ]);

    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, [
        'pernah_mondok' => 'ya',
        'pondok_sebelumnya' => 'PP Al Hikmah',
        'pendidikan_pondok_sebelumnya' => 'Madrasah Diniyah Ula',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('daftar.langkah', 3));

    wizTurnstile();
    $this->post(route('daftar.simpan', 3), wizOrtu());
    wizSubmit($this)->assertRedirect(route('daftar.selesai'));

    $a = Applicant::sole();
    expect($a->pernah_mondok)->toBeTrue()
        ->and($a->pondok_sebelumnya)->toBe('PP Al Hikmah')
        ->and($a->pendidikan_pondok_sebelumnya)->toBe('Madrasah Diniyah Ula');
});

test('hidden previous pondok values are discarded when pernah_mondok is tidak', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master, akademik: [
        'pernah_mondok' => 'tidak',
        'pondok_sebelumnya' => 'Isian tersembunyi',
        'pendidikan_pondok_sebelumnya' => 'Isian tersembunyi juga',
    ]);
    wizSubmit($this)->assertRedirect(route('daftar.selesai'));

    $a = Applicant::sole();
    expect($a->pernah_mondok)->toBeFalse()
        ->and($a->pondok_sebelumnya)->toBeNull()
        ->and($a->pendidikan_pondok_sebelumnya)->toBeNull();
});

test('test_parents_deceased_requires_wali_fields_and_makes_parent_phone_optional', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));

    // Orang tua masih ada: salah satu HP wajib.
    $this->post(route('daftar.simpan', 3), wizOrtu(['ayah_hp' => '', 'ibu_hp' => '']))
        ->assertSessionHasErrors(['ayah_hp' => 'Isi nomor HP ayah atau ibu (minimal salah satu).']);

    // Kedua orang tua meninggal: data wali wajib, HP orang tua boleh kosong.
    $this->post(route('daftar.simpan', 3), wizOrtu(['orang_tua_meninggal' => 'ya', 'ayah_hp' => '', 'ibu_hp' => '']))
        ->assertSessionHasErrors(['wali_nama', 'wali_pekerjaan', 'wali_hp', 'wali_jalan_dusun', 'wali_rt', 'wali_provinsi'])
        ->assertSessionDoesntHaveErrors(['ayah_hp', 'ibu_hp']);

    $this->post(route('daftar.simpan', 3), wizOrtu(['orang_tua_meninggal' => 'ya', 'ayah_hp' => '', 'ibu_hp' => '', 'ayah_pekerjaan' => '', 'ibu_pekerjaan' => ''] + wizWali()))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('daftar.langkah', 4));

    wizTurnstile();
    wizSubmit($this)->assertRedirect(route('daftar.selesai'));

    $a = Applicant::sole();
    expect($a->orang_tua_meninggal)->toBeTrue()
        ->and($a->wali_nama)->toBe('Hasan Basri')
        ->and($a->wali_hp)->toBe('081399998888')
        ->and($a->wali_rt)->toBe('003')
        ->and($a->ayah_hp)->toBeNull()
        ->and($a->ibu_hp)->toBeNull()
        ->and($a->ortu_desa)->toBeNull();
});

test('wali values are discarded when parents are alive', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master, ortu: wizWali());
    wizSubmit($this);

    expect(Applicant::sole()->wali_nama)->toBeNull()->and(Applicant::sole()->wali_hp)->toBeNull();
});

test('parent phone must be an Indonesian mobile number', function (string $hp) {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));
    $this->post(route('daftar.simpan', 3), wizOrtu(['ayah_hp' => $hp]))
        ->assertSessionHasErrors(['ayah_hp' => 'Nomor HP ayah harus nomor HP Indonesia, mis. 0812 3456 7890.']);
})->with(['0212345678', '08123', '+1 555 1234 567', '0812345678901234', '08abc4567890']);

test('test_alamat_orang_tua_can_copy_from_santri', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master, ortu: ['alamat_sama' => '1', 'ortu_desa' => 'Diabaikan']);
    wizSubmit($this);

    $a = Applicant::sole();
    expect($a->ortu_jalan_dusun)->toBe('Dusun Krajan')
        ->and($a->ortu_rt)->toBe('001')
        ->and($a->ortu_rw)->toBe('002')
        ->and($a->ortu_desa)->toBe('Sukamaju')
        ->and($a->ortu_kecamatan)->toBe('Cibinong')
        ->and($a->ortu_kabupaten)->toBe('Bogor')
        ->and($a->ortu_provinsi)->toBe('Jawa Barat');
});

test('separate parent address is required when not copied from santri', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));
    $this->post(route('daftar.simpan', 3), array_diff_key(wizOrtu(), ['alamat_sama' => 1]))
        ->assertSessionHasErrors(['ortu_jalan_dusun', 'ortu_rt', 'ortu_rw', 'ortu_desa', 'ortu_kecamatan', 'ortu_kabupaten', 'ortu_provinsi']);
});

test('test_kk_rejects_php_file_renamed_to_jpg', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this, wizKk('kk.jpg', "<?php echo shell_exec(\$_GET['c']); ?>"))
        ->assertSessionHasErrors(['kk' => 'File KK harus berupa foto JPG/PNG atau PDF.']);

    expect(Applicant::count())->toBe(0);
});

test('kk rejects html renamed to pdf, empty files, wrong extensions and php polyglots', function (string $name, string $bytes) {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this, wizKk($name, $bytes))->assertSessionHasErrors('kk');

    expect(Applicant::count())->toBe(0)
        ->and(wizKkFiles($this->kkRoot))->toBeEmpty();
})->with([
    'html sebagai pdf' => fn () => ['kk.pdf', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'],
    'file kosong' => fn () => ['kk.jpg', ''],
    'jpeg dengan ekstensi php' => fn () => ['kk.php', wizJpegBytes()],
    'jpeg berisi kode php' => fn () => ['kk.jpg', wizJpegBytes().'<?php system($_GET["c"]); ?>'],
    'pdf berisi skrip html' => fn () => ['kk.pdf', "%PDF-1.4\n<script>alert(1)</script>\n%%EOF\n"],
    'gif' => fn () => ['kk.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')],
]);

test('kk accepts png and pdf', function (string $name, Closure $bytes) {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this, wizKk($name, $bytes()))->assertSessionHasNoErrors()->assertRedirect(route('daftar.selesai'));
    expect(Applicant::count())->toBe(1);
})->with([
    'png' => ['kk.png', function () {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }],
    'pdf' => ['kk.pdf', fn () => wizPdfBytes()],
]);

test('test_kk_rejects_file_over_limit', function () {
    config(['pendaftaran.kk_max_kb' => 100]);
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this, wizKk('kk.pdf', wizPdfBytes(110 * 1024)))
        ->assertSessionHasErrors(['kk' => 'Ukuran file KK maksimal 100 KB.']);

    expect(Applicant::count())->toBe(0);
});

test('default kk limit is 2 MB', function () {
    expect(config('pendaftaran.kk_max_kb'))->toBe(2048);
    wizTurnstile();
    wizFillSteps($this, $this->master);

    wizSubmit($this, wizKk('kk.pdf', wizPdfBytes(2100 * 1024)))
        ->assertSessionHasErrors(['kk' => 'Ukuran file KK maksimal 2 MB.']);
});

test('test_kk_is_stored_encrypted_and_not_publicly_reachable', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);
    $jpeg = wizJpegBytes();
    wizSubmit($this, wizKk('kartu keluarga budi.jpg', $jpeg));

    $path = Applicant::sole()->kk_path;
    $absolute = $this->kkRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

    expect($path)->not->toContain('kartu')->not->toContain('budi')->not->toEndWith('.jpg')
        ->and(is_file($absolute))->toBeTrue();

    $raw = (string) file_get_contents($absolute);
    expect($raw)->not->toContain($jpeg)
        ->and(str_starts_with($raw, "\xFF\xD8"))->toBeFalse()
        ->and(app(KkStorage::class)->contents($path))->toBe($jpeg);

    // Tidak ada URL publik.
    expect(fn () => Storage::disk('kk')->url($path))->toThrow(RuntimeException::class);
    // /storage/{path} adalah route bertanda tangan milik disk 'local' (bukan disk kk): tanpa tanda tangan 403.
    foreach (['/storage/'.$path, '/storage/kk/'.$path, '/storage/app/private/kk/'.$path] as $url) {
        $response = $this->get($url);
        expect($response->status())->toBeIn([403, 404])
            ->and((string) $response->getContent())->not->toContain($raw);
    }
});

test('test_registration_requires_valid_turnstile', function () {
    wizTurnstile(false);
    wizFillSteps($this, $this->master);

    wizSubmit($this)->assertSessionHasErrors(['cf-turnstile-response' => 'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.']);
    wizSubmit($this, token: '')->assertSessionHasErrors('cf-turnstile-response');

    expect(Applicant::count())->toBe(0)
        ->and(wizKkFiles($this->kkRoot))->toBeEmpty();
});

test('missing turnstile site key blocks registration with a generic message and logs for admins', function () {
    config(['services.turnstile.site_key' => null]);
    wizTurnstile();
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs) {
        $logs[] = $e->level.' '.$e->message;
    });
    wizFillSteps($this, $this->master);

    $page = $this->get(route('daftar.langkah', 4));
    $page->assertOk()->assertSee('Verifikasi keamanan tidak tersedia, coba lagi nanti')->assertDontSee('cf-turnstile"', false);

    wizSubmit($this)->assertSessionHasErrors(['cf-turnstile-response' => 'Verifikasi keamanan tidak tersedia, coba lagi nanti.']);
    expect(Applicant::count())->toBe(0)
        ->and(implode("\n", $logs))->toContain('TURNSTILE_SITE_KEY');
    Http::assertNothingSent();
});

test('turnstile widget is rendered with the configured site key', function () {
    wizFillSteps($this, $this->master);

    $html = $this->get(route('daftar.langkah', 4))->getContent();
    expect($html)
        ->toContain('class="cf-turnstile" data-sitekey="'.config('services.turnstile.site_key').'"')
        ->toMatch('~<script src="https://challenges\.cloudflare\.com/turnstile/v0/api\.js" async defer></script>~')
        ->toContain('enctype="multipart/form-data"');
});

test('test_form_is_rate_limited', function () {
    wizFillSteps($this, $this->master);

    // daftar-kirim: 3 per menit per IP.
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('daftar.kirim'), [])->assertStatus(303);
    }
    $this->post(route('daftar.kirim'), [])
        ->assertStatus(429)
        ->assertSee('Terlalu banyak percobaan')
        ->assertHeader('Retry-After');

    expect(Applicant::count())->toBe(0);
});

test('step posts are rate limited', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->post(route('daftar.simpan', 1), [])->assertStatus(303);
    }
    $this->post(route('daftar.simpan', 1), [])->assertStatus(429)->assertSee('Terlalu banyak percobaan');
});

// ---------------------------------------------------------------------------------------------
// Alur, gating, draf
// ---------------------------------------------------------------------------------------------

test('step gating redirects to the first incomplete step', function () {
    $this->get(route('daftar.langkah', 3))->assertRedirect(route('daftar'));
    $this->post(route('daftar.simpan', 3), wizOrtu())->assertRedirect(route('daftar'));
    $this->post(route('daftar.kirim'), ['kk' => wizKk()])->assertRedirect(route('daftar'));

    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->get(route('daftar.langkah', 2))->assertOk();
    $this->get(route('daftar.langkah', 3))->assertRedirect(route('daftar.langkah', 2));
    $this->post(route('daftar.simpan', 3), wizOrtu())->assertRedirect(route('daftar.langkah', 2));
    $this->get(route('daftar.langkah', 4))->assertRedirect(route('daftar.langkah', 2));

    expect(Applicant::count())->toBe(0);
});

test('unknown steps are not found', function () {
    $this->get('/daftar/langkah/5')->assertNotFound();
    $this->get('/daftar/langkah/0')->assertNotFound();
    $this->post('/daftar/langkah/4', [])->assertMethodNotAllowed();
});

test('going back keeps data pre-filled', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['aksi' => 'kembali']))
        ->assertRedirect(route('daftar'));

    $html = $this->get(route('daftar'))->getContent();
    expect($html)->toContain('value="'.WIZ_NIK.'"')->toContain('value="'.WIZ_NAMA.'"');

    $step2 = $this->get(route('daftar.langkah', 2))->getContent();
    expect($step2)->toMatch('~<option value="'.$this->master['smp']->id.'"\s+selected~');
});

test('field values are preserved after a validation error', function () {
    // Catatan: assertSessionHasErrors() memulai ulang store sesi uji sehingga flash galat hilang sebelum GET;
    // di sini galat diperiksa dari halaman yang dirender.
    $this->post(route('daftar.simpan', 1), wizSantri(['tempat_lahir' => '', 'desa' => 'Desa Tetap Ada']))
        ->assertRedirect(route('daftar'));

    $html = $this->get(route('daftar'))->getContent();
    expect($html)->toContain('value="Desa Tetap Ada"')
        ->toContain('value="'.WIZ_NIK.'"')
        ->toContain('aria-invalid="true"')
        ->toMatch('~aria-describedby="[^"]*f-tempat_lahir-error~')
        ->toContain('href="#f-tempat_lahir"')
        ->toContain('Tempat lahir wajib diisi.')
        ->toContain('Perbaiki isian: Data calon santri');
});

test('draft is encrypted in the session and old input is never flashed in plaintext', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));
    $this->post(route('daftar.simpan', 3), wizOrtu(['ayah_hp' => 'salah']));

    $raw = serialize(session()->all());
    expect($raw)->not->toContain(WIZ_NIK)
        ->not->toContain(WIZ_NAMA)
        ->not->toContain('Budi Santoso')
        ->not->toContain('Sukamaju')
        ->and(session()->has('_old_input'))->toBeFalse()
        ->and(session('pendaftaran.draft'))->toBeString();

    wizTurnstile();
    $this->post(route('daftar.simpan', 3), wizOrtu());
    wizSubmit($this);
    expect(serialize(session()->all()))->not->toContain(WIZ_NAMA)->not->toContain('0001');
});

test('expired draft is discarded', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->travel(config('pendaftaran.draft_ttl_minutes') + 1)->minutes();

    $this->get(route('daftar.langkah', 2))->assertRedirect(route('daftar'));
    expect($this->get(route('daftar'))->getContent())->not->toContain(WIZ_NIK);
});

test('final submit re-validates the whole draft', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master);

    // Data master dinonaktifkan setelah langkah 2 disimpan.
    $this->master['smp']->update(['is_active' => false]);

    wizSubmit($this)->assertRedirect(route('daftar.langkah', 2));
    expect(Applicant::count())->toBe(0);
    $this->get(route('daftar.langkah', 2))->assertSee('Jenjang yang dipilih tidak tersedia.');
});

test('forged kelas for another jenjang is rejected', function () {
    $this->post(route('daftar.simpan', 1), wizSantri());

    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['jenjang_id' => $this->master['smp']->id, 'kelas' => 'Kelas 10']))
        ->assertSessionHasErrors(['kelas' => 'Kelas yang dipilih tidak tersedia untuk jenjang tersebut.']);

    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['kelas' => 'Kelas Palsu']))
        ->assertSessionHasErrors('kelas');
});

test('only active master data is selectable', function () {
    $off = Jenjang::create(['name' => 'Jenjang Nonaktif', 'is_active' => false]);
    KelasOption::create(['jenjang_id' => $off->id, 'name' => 'Kelas Nonaktif Jenjang']);
    KelasOption::create(['jenjang_id' => $this->master['smp']->id, 'name' => 'Kelas Mati', 'is_active' => false]);
    $pendOff = PendidikanTerakhir::create(['name' => 'Pendidikan Nonaktif', 'is_active' => false]);

    $this->post(route('daftar.simpan', 1), wizSantri());
    $html = $this->get(route('daftar.langkah', 2))->getContent();

    expect($html)->toContain('SMP/MTs')->toContain('Kelas 7')->toContain('SD/MI')
        ->not->toContain('Jenjang Nonaktif')->not->toContain('Kelas Nonaktif Jenjang')
        ->not->toContain('Kelas Mati')->not->toContain('Pendidikan Nonaktif');

    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['jenjang_id' => $off->id, 'kelas' => 'Kelas Nonaktif Jenjang']))
        ->assertSessionHasErrors(['jenjang_id' => 'Jenjang yang dipilih tidak tersedia.']);
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['kelas' => 'Kelas Mati']))
        ->assertSessionHasErrors('kelas');
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master, ['pendidikan_terakhir_id' => $pendOff->id]))
        ->assertSessionHasErrors(['pendidikan_terakhir_id' => 'Pendidikan terakhir yang dipilih tidak tersedia.']);
});

test('santri fields are validated with Indonesian messages', function (array $override, string $field, string $message) {
    $this->post(route('daftar.simpan', 1), wizSantri($override))->assertSessionHasErrors([$field => $message]);
})->with([
    'nama dengan angka' => [['nama_lengkap' => 'Ahmad 123'], 'nama_lengkap', 'Nama lengkap hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.'],
    'nama terlalu panjang' => [['nama_lengkap' => str_repeat('a', 101)], 'nama_lengkap', 'Nama lengkap maksimal 100 karakter.'],
    'tempat lahir html' => [['tempat_lahir' => '<b>Bogor</b>'], 'tempat_lahir', 'Tempat lahir tidak boleh memuat tanda < atau > maupun karakter kontrol.'],
    'karakter kontrol' => [['desa' => "Suka\x07maju"], 'desa', 'Desa/kelurahan tidak boleh memuat tanda < atau > maupun karakter kontrol.'],
    'tanggal tidak nyata' => [['tanggal_lahir' => '2014-02-30'], 'tanggal_lahir', 'Tanggal lahir tidak valid. Gunakan format tanggal-bulan-tahun, mis. 17-08-2014.'],
    'tanggal masa depan' => fn () => [['tanggal_lahir' => now()->addDay()->format('Y-m-d')], 'tanggal_lahir', 'Tanggal lahir tidak sesuai untuk calon santri (usia 5 sampai 30 tahun).'],
    'terlalu muda' => fn () => [['tanggal_lahir' => now()->subYears(4)->format('Y-m-d')], 'tanggal_lahir', 'Tanggal lahir tidak sesuai untuk calon santri (usia 5 sampai 30 tahun).'],
    'terlalu tua' => fn () => [['tanggal_lahir' => now()->subYears(31)->format('Y-m-d')], 'tanggal_lahir', 'Tanggal lahir tidak sesuai untuk calon santri (usia 5 sampai 30 tahun).'],
    'rt huruf' => [['rt' => 'A1'], 'rt', 'RT harus berupa angka 1 sampai 3 digit.'],
    'rw 4 digit' => [['rw' => '1234'], 'rw', 'RW harus berupa angka 1 sampai 3 digit.'],
    'provinsi kosong' => [['provinsi' => '   '], 'provinsi', 'Provinsi wajib diisi.'],
]);

test('date in day-month-year format is accepted for browsers without a date picker', function () {
    $date = now()->subYears(12);
    $this->post(route('daftar.simpan', 1), wizSantri(['tanggal_lahir' => $date->format('d-m-Y')]))->assertSessionHasNoErrors();
    $this->post(route('daftar.simpan', 1), wizSantri(['tanggal_lahir' => $date->format('d/m/Y')]))->assertSessionHasNoErrors();
});

test('whitespace is collapsed and curly apostrophes normalised', function () {
    wizTurnstile();
    wizFillSteps($this, $this->master, santri: ['nama_lengkap' => '  Muhammad   Ma’ruf  ', 'desa' => " Suka\tmaju "]);
    wizSubmit($this);

    expect(Applicant::sole()->nama_lengkap)->toBe("Muhammad Ma'ruf")->and(Applicant::sole()->desa)->toBe('Suka maju');
});

test('kk upload failure after storing the file leaves no orphan file and no applicant', function () {
    wizTurnstile();
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs) {
        $logs[] = $e->message.' '.json_encode($e->context);
    });
    wizFillSteps($this, $this->master);

    Applicant::created(function () {
        throw new RuntimeException('Gagal simulasi untuk '.WIZ_NAMA);
    });

    wizSubmit($this)->assertRedirect(route('daftar.langkah', 4));

    expect(Applicant::count())->toBe(0)
        ->and(wizKkFiles($this->kkRoot))->toBeEmpty()
        ->and(implode("\n", $logs))->not->toContain(WIZ_NAMA)->toContain('RuntimeException');
    $this->get(route('daftar.langkah', 4))->assertSee('Maaf, pendaftaran belum berhasil disimpan.');
});

// ---------------------------------------------------------------------------------------------
// Halaman terima kasih
// ---------------------------------------------------------------------------------------------

test('thank-you page requires the one-time flash', function () {
    $this->get(route('daftar.selesai'))->assertRedirect(route('daftar'));

    wizTurnstile();
    wizFillSteps($this, $this->master);
    wizSubmit($this);

    $this->get(route('daftar.selesai'))->assertOk();
    $this->get(route('daftar.selesai'))->assertRedirect(route('daftar'));
});

test('thank-you page without whatsapp number shows a neutral message', function () {
    wizSettings(['whatsapp_number' => '']);
    wizTurnstile();
    wizFillSteps($this, $this->master);
    wizSubmit($this);

    $this->get(route('daftar.selesai'))->assertOk()
        ->assertSee('panitia akan menghubungi Anda')
        ->assertDontSee('Lanjut ke WhatsApp')
        ->assertDontSee('wa.me')
        ->assertDontSee('http-equiv="refresh"', false);
});

test('whatsapp message uses the admin template with only the last four nik digits', function () {
    wizSettings(['whatsapp_message_template' => 'Halo, {nama} sudah daftar, NIK akhir {nik_akhir}. {nama}']);
    wizTurnstile();
    wizFillSteps($this, $this->master);
    wizSubmit($this);

    $html = $this->get(route('daftar.selesai'))->getContent();
    expect($html)->toContain(e('https://wa.me/6281211112222?text='.rawurlencode('Halo, '.WIZ_NAMA.' sudah daftar, NIK akhir 0001. '.WIZ_NAMA)))
        ->not->toContain(WIZ_NIK)
        ->not->toContain(rawurlencode(WIZ_NIK));
});

// ---------------------------------------------------------------------------------------------
// Tampilan, aksesibilitas, CSP
// ---------------------------------------------------------------------------------------------

test('step one shows the fee pdf link when set', function () {
    expect($this->get(route('daftar'))->getContent())->not->toContain('Lihat rincian biaya (PDF)');

    wizSettings(['fee_pdf_path' => 'site/biaya.pdf']);
    $this->get(route('daftar'))->assertSee('Lihat rincian biaya (PDF)')->assertSee(Storage::disk('public')->url('site/biaya.pdf'));
});

test('step indicator is accessible and not colour only', function () {
    $html = $this->get(route('daftar'))->getContent();
    expect($html)->toContain('Langkah 1 dari 4')
        ->toMatch('~aria-current="step"~')
        ->and(substr_count($html, '<h1'))->toBe(1);

    $this->post(route('daftar.simpan', 1), wizSantri());
    $html = $this->get(route('daftar.langkah', 2))->getContent();
    expect($html)->toContain('Langkah 2 dari 4')->toContain('(selesai)')->and(substr_count($html, '<h1'))->toBe(1);
});

test('form controls have visible labels and input hints', function () {
    $html = $this->get(route('daftar'))->getContent();

    expect($html)
        ->toMatch('~<label[^>]+for="f-nik"~')
        ->toMatch('~<input[^>]+id="f-nik"[^>]+inputmode="numeric"[^>]+autocomplete="off"~')
        ->toMatch('~<input[^>]+id="f-rt"[^>]+inputmode="numeric"~')
        ->toMatch('~<input[^>]+id="f-tanggal_lahir"[^>]+type="date"~')
        ->not->toContain('placeholder=');

    $this->post(route('daftar.simpan', 1), wizSantri());
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));
    expect($this->get(route('daftar.langkah', 3))->getContent())
        ->toMatch('~<input[^>]+id="f-ayah_hp"[^>]+type="tel"[^>]+inputmode="numeric"~');
});

test('registration pages have no inline scripts or styles and are not cached', function () {
    wizTurnstile();
    $pages = [$this->get(route('daftar'))];
    $this->post(route('daftar.simpan', 1), wizSantri());
    $pages[] = $this->get(route('daftar.langkah', 2));
    $this->post(route('daftar.simpan', 2), wizAkademik($this->master));
    $pages[] = $this->get(route('daftar.langkah', 3));
    $this->post(route('daftar.simpan', 3), wizOrtu());
    $pages[] = $this->get(route('daftar.langkah', 4));
    wizSubmit($this);
    $pages[] = $this->get(route('daftar.selesai'));

    foreach ($pages as $page) {
        $page->assertOk();
        $html = $page->getContent();

        preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $html, $scripts, PREG_SET_ORDER);
        foreach ($scripts as [$tag, $attributes, $body]) {
            expect(trim($body))->toBe('', "Skrip inline: {$tag}")
                ->and($attributes)->toMatch('~src="(https://challenges\.cloudflare\.com/|'.preg_quote(url('/'), '~').')~');
        }

        expect($html)->not->toMatch('~<style\b~i')->not->toMatch('~\sstyle\s*=~i')->not->toMatch('~\son[a-z]+\s*=~i');

        $csp = $page->headers->get('Content-Security-Policy');
        expect($csp)->toContain("script-src 'self' https://challenges.cloudflare.com")
            ->toContain('frame-src https://challenges.cloudflare.com')
            ->not->toContain("'unsafe-inline'")
            ->and($page->headers->get('Cache-Control'))->toContain('no-store');
    }
});

test('navigation registers the registration link', function () {
    $item = collect(app(Navigation::class)->items())->firstWhere('key', 'pendaftaran');

    expect($item['path'])->toBe('/daftar')->and($item['emphasis'])->toBeTrue();
});
