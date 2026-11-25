<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Pendaftaran\Models\Applicant;

uses(RefreshDatabase::class);

function normPath(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/').'/';
}

/*
 * Catatan kejujuran: tes PHP hanya membuktikan perilaku aplikasi dan konfigurasi. Apakah Nginx/aaPanel
 * benar-benar menolak /.env atau /storage di server produksi harus diperiksa dengan curl setelah deploy
 * (lihat docs/DEPLOY-aapanel.md, bagian daftar periksa keamanan pasca-deploy).
 */

test('test_private_disk_has_no_public_url', function () {
    $kk = config('filesystems.disks.kk');
    $kkRoot = normPath($kk['root']);

    // Root KK di luar public/ dan di luar root disk mana pun (terutama 'local' yang punya route serve).
    expect($kkRoot)->not->toStartWith(normPath(public_path()));

    foreach (config('filesystems.disks') as $name => $disk) {
        if ($name === 'kk' || ! isset($disk['root'])) {
            continue;
        }

        expect($kkRoot)->not->toStartWith(normPath($disk['root']), "kk berada di dalam root disk '{$name}'")
            ->and(normPath($disk['root']))->not->toStartWith($kkRoot);
    }

    // Disk 'local' tidak dilayani lewat route storage.
    expect(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and($kk)->not->toHaveKey('url');

    expect(fn () => Storage::disk('kk')->url('2026/11/x.kk'))->toThrow(RuntimeException::class)
        ->and(fn () => Storage::disk('kk')->temporaryUrl('2026/11/x.kk', now()->addMinute()))->toThrow(RuntimeException::class);

    // Satu-satunya symlink publik yang dikonfigurasi menunjuk ke disk public.
    $links = [];
    foreach (config('filesystems.links') as $link => $target) {
        $links[normPath($link)] = normPath($target);
    }
    expect($links)->toBe([normPath(public_path('storage')) => normPath(storage_path('app/public'))]);
});

test('test_env_and_storage_not_reachable_via_http', function () {
    $paths = [
        '/.env',
        '/.env.example',
        '/.git/config',
        '/composer.json',
        '/storage/app/private/kk/2026/11/x.kk',
        '/storage/app/kk/2026/11/x.kk',
        '/storage/kk/2026/11/x.kk',
        '/storage/logs/laravel.log',
        '/storage/framework/sessions/x',
        '/vendor/autoload.php',
        '/local/kk/2026/11/x.kk',
    ];

    foreach ($paths as $path) {
        $this->get($path)->assertNotFound();
    }

    // Tidak ada route yang melayani file dari disk lokal/KK.
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.kk'))->toBeFalse();

    // public/ tidak memuat rahasia maupun symlink ke direktori privat.
    foreach (['.env', 'storage/app', 'storage/logs', 'vendor', 'composer.json'] as $name) {
        expect(file_exists(public_path($name)))->toBeFalse("public/{$name} seharusnya tidak ada");
    }

    $link = public_path('storage');
    if (is_link($link)) {
        expect(normPath((string) realpath($link)))->toBe(normPath((string) realpath(storage_path('app/public'))));
    }
});

test('test_login_is_rate_limited', function () {
    Filament::setCurrentPanel('admin');

    $user = User::factory()->create(['password' => Hash::make('sandi-benar-123')]);

    // 5 percobaan gagal per menit per IP dibolehkan (Filament Login: rateLimit(5)); ke-6 diblokir
    // walau sandinya sudah benar.
    $component = Livewire::test(Login::class);

    foreach (range(1, 5) as $i) {
        $component->fillForm(['email' => $user->email, 'password' => 'salah-'.$i])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    $component->fillForm(['email' => $user->email, 'password' => 'sandi-benar-123'])
        ->call('authenticate')
        ->assertNotified();

    expect(Filament::auth()->check())->toBeFalse();
});

test('test_sensitive_models_hidden_from_public_routes', function () {
    // Semua route yang menyentuh data pendaftar, KK, user, atau role berada di bawah admin/ dan
    // mewajibkan login; tidak ada yang publik.
    $sensitive = ['pendaftar', 'applicant', 'kk', 'users', 'roles', 'shield', 'export', 'audit', 'activity'];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (str_starts_with($uri, 'livewire-') || str_starts_with($uri, 'filament/')) {
            continue;
        }

        $segments = explode('/', $uri);
        $touches = array_intersect($segments, $sensitive) !== [];

        if (! $touches) {
            continue;
        }

        expect($segments[0])->toBe('admin', "route sensitif di luar admin: {$uri}");
        expect(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_contains($m, 'Authenticate')))
            ->toBeTrue("route sensitif tanpa Authenticate: {$uri}");
    }

    // Halaman publik tidak membocorkan data pendaftar.
    $applicant = Applicant::factory()->create(['nama_lengkap' => 'Rahasia Santri Unik']);

    foreach (['/', '/berita', '/daftar', '/daftar/terima-kasih'] as $path) {
        $body = (string) $this->get($path)->getContent();

        expect($body)->not->toContain('Rahasia Santri Unik')
            ->and($body)->not->toContain((string) $applicant->nik);
    }

    // Tamu diarahkan ke login untuk halaman admin dan unduhan KK.
    $this->get('/admin/pendaftar')->assertRedirect();
    $this->get('/admin/pendaftar/'.$applicant->getKey().'/kk')->assertRedirect();
    $this->get('/admin/users')->assertRedirect();
});
