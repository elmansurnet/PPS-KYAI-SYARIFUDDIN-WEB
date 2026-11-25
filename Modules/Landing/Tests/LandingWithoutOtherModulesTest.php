<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Settings\SiteSettings;
use Modules\Core\Support\Navigation;
use Modules\Landing\Database\Seeders\LandingSeeder;
use Modules\Landing\Models\LandingSection;
use Modules\Landing\Providers\LandingServiceProvider;
use Tests\TestCase;

/*
 * Aturan modul (CLAUDE.md): modul tetap jalan bila modul lain dinonaktifkan. Aplikasi tes di file ini
 * di-boot HANYA dengan Landing (Berita dan Pendaftaran tidak dimuat sama sekali). Tanpa RefreshDatabase:
 * migrate dijalankan pada koneksi memori milik aplikasi ini saja (lihat TestCase::$modulesOverride).
 */

beforeAll(fn () => TestCase::$modulesOverride = [LandingServiceProvider::class]);
afterAll(fn () => TestCase::$modulesOverride = null);

beforeEach(function () {
    $this->artisan('migrate');
    $this->seed(DatabaseSeeder::class);
});

test('berita and pendaftaran are really not loaded', function () {
    expect(app(Navigation::class)->has('berita'))->toBeFalse()
        ->and(app(Navigation::class)->has('pendaftaran'))->toBeFalse()
        ->and(app('router')->has('daftar'))->toBeFalse()
        ->and(view()->exists('berita::index'))->toBeFalse()
        ->and(Schema::hasTable('applicants'))->toBeFalse()
        ->and(Schema::hasTable('posts'))->toBeFalse();

    $this->get('/daftar')->assertNotFound();
    $this->get('/berita')->assertNotFound();
});

test('home renders without registration or news links when those modules are disabled', function () {
    expect(LandingSection::where('key', 'hero')->exists())->toBeTrue();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->not->toContain('/daftar')
        ->not->toContain('/berita')
        ->not->toContain('Daftar sekarang')
        ->not->toContain('Daftar santri baru')
        ->not->toContain('Berita terbaru')
        ->toContain('Daftarkan santri baru') // teks ajakan tetap tampil
        ->toContain('href="'.url('/').'"');
});

test('fee pdf link still works on the home page without the registration module', function () {
    Storage::fake('public');
    $settings = app(SiteSettings::class);
    $settings->fee_pdf_path = 'site/biaya.pdf';
    $settings->save();

    $this->get('/')->assertOk()
        ->assertSee('Unduh rincian biaya (PDF)')
        ->assertSee('href="'.Storage::disk('public')->url('site/biaya.pdf').'"', false);
});

test('landing seeder runs standalone', function () {
    $this->seed(LandingSeeder::class);

    expect(LandingSection::count())->toBe(6);
});
