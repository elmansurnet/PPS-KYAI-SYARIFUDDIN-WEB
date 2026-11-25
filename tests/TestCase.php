<?php

namespace Tests;

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengganti config('modules.enabled') saat aplikasi tes dibuat (null = config/modules.php). Dipakai tes
     * "modul tetap jalan bila modul lain nonaktif": provider modul yang tidak tercantum benar-benar tidak
     * dimuat (route, view, migrasi, navigasi, plugin Filament). Setel di beforeAll dan kembalikan ke null di
     * afterAll; file tes seperti itu TIDAK boleh memakai RefreshDatabase (koneksi SQLite memori dipakai
     * bersama antar-tes), melainkan menjalankan migrate sendiri.
     *
     * @var list<class-string>|null
     */
    public static ?array $modulesOverride = null;

    public function createApplication()
    {
        if (static::$modulesOverride === null) {
            return parent::createApplication();
        }

        $modules = static::$modulesOverride;
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, fn ($app) => $app['config']->set('modules.enabled', $modules));
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh melakukan panggilan HTTP nyata (mis. Turnstile); wajib Http::fake().
        Http::preventStrayRequests();

        // Penanda statis Livewire ("komponen sudah dirender di request ini") tidak ikut direset antar-tes,
        // sehingga Livewire::test() di tes sebelumnya membuat halaman publik di tes berikutnya disisipi
        // <style> Livewire. Di produksi setiap request berjalan di proses baru, jadi ini hanya urusan tes.
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;
        SupportAutoInjectedAssets::$forceAssetInjection = false;
    }
}
