<?php

namespace Modules\Core\Providers;

use Filament\Contracts\Plugin;
use InvalidArgumentException;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Support\Navigation;
use Modules\Core\Support\NullLatestNewsProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /** @var array<int, ModuleServiceProvider> */
    private array $moduleProviders = [];

    protected function moduleName(): string
    {
        return 'Core';
    }

    public function register(): void
    {
        $this->app->bindIf(LatestNewsProvider::class, NullLatestNewsProvider::class);

        // Tautan bawaan navbar publik. Modul Landing, Berita, dan Pendaftaran boleh menimpa
        // item dengan kunci yang sama dari provider-nya sendiri (lihat Navigation).
        $this->app->singleton(Navigation::class, fn () => (new Navigation)
            ->add('beranda', 'Beranda', '/', 10)
            ->add('berita', 'Berita', '/berita', 20)
            ->add('pendaftaran', 'Pendaftaran', '/daftar', 90, emphasis: true));

        $this->registerEnabledModules();
    }

    /**
     * Daftarkan provider dari config('modules.enabled'). Aman dipanggil ulang.
     */
    public function registerEnabledModules(): void
    {
        foreach ((array) config('modules.enabled', []) as $class) {
            if (! class_exists($class)) {
                throw new InvalidArgumentException("Provider modul tidak ditemukan: {$class} (config 'modules.enabled').");
            }

            $provider = $this->app->register($class);

            if ($provider instanceof ModuleServiceProvider && ! in_array($provider, $this->moduleProviders, true)) {
                $this->moduleProviders[] = $provider;
            }
        }
    }

    /**
     * Plugin Filament dari semua modul aktif (untuk panel admin).
     *
     * @return array<int, Plugin>
     */
    public function registeredFilamentPlugins(): array
    {
        $plugins = [];

        foreach ($this->moduleProviders as $provider) {
            if ($plugin = $provider->getFilamentPlugin()) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
