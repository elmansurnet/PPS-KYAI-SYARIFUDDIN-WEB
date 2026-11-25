<?php

namespace Modules\Core\Providers;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Support\Navigation;
use Modules\Core\Support\NullLatestNewsProvider;
use Spatie\Permission\Models\Role;

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

        // Core hanya mendaftarkan Beranda. Modul fitur menambah tautannya sendiri dari provider-nya
        // (Berita: 'berita', Pendaftaran: 'pendaftaran'), jadi modul yang nonaktif tidak meninggalkan
        // tautan yang berujung 404 (lihat Navigation).
        $this->app->singleton(Navigation::class, fn () => (new Navigation)
            ->add('beranda', 'Beranda', '/', 10));

        $this->registerEnabledModules();
    }

    public function boot(): void
    {
        parent::boot();

        $this->protectSuperadminRole();
    }

    public static function superadminRoleName(): string
    {
        return (string) config('filament-shield.super_admin.name', 'superadmin');
    }

    /**
     * Gate superadmin bekerja berdasarkan NAMA role (filament-shield.super_admin.name) dan Gate::before
     * melewati RolePolicy, jadi role itu dijaga di tingkat model: tidak boleh diganti nama/guard atau dihapus.
     */
    private function protectSuperadminRole(): void
    {
        $roleClass = config('permission.models.role', Role::class);

        $refuse = function (string $message): never {
            if (Filament::isServing()) {
                Notification::make()->danger()->title($message)->send();
            }

            throw ValidationException::withMessages(['data.name' => $message]);
        };

        $roleClass::updating(function (Model $role) use ($refuse) {
            if ($role->getOriginal('name') === self::superadminRoleName() && $role->isDirty(['name', 'guard_name'])) {
                $refuse('Role superadmin tidak boleh diganti nama atau guard-nya.');
            }
        });

        $roleClass::deleting(function (Model $role) use ($refuse) {
            // Nama asli dari database, bukan atribut yang mungkin sudah diubah di memori.
            if (in_array(self::superadminRoleName(), [$role->getOriginal('name'), $role->getAttribute('name')], true)) {
                $refuse('Role superadmin tidak boleh dihapus.');
            }
        });
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
