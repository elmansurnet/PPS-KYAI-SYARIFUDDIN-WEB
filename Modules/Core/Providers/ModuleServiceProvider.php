<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function moduleName(): string;

    /**
     * Plugin Filament modul, bila ada panel admin.
     *
     * Tipe kembalian sebenarnya \Filament\Contracts\Plugin|null. Dideklarasikan
     * ?object agar kelas ini tetap bisa dimuat saat Filament belum terpasang.
     *
     * @return \Filament\Contracts\Plugin|null
     */
    protected function filamentPlugin(): ?object
    {
        return null;
    }

    public function getFilamentPlugin(): ?object
    {
        return $this->filamentPlugin();
    }

    public function boot(): void
    {
        $path = $this->modulePath();

        if (is_dir($path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($path.'/Database/Migrations');
        }

        if (is_file($path.'/Routes/web.php')) {
            $this->loadRoutesFrom($path.'/Routes/web.php');
        }

        if (is_dir($path.'/Resources/views')) {
            $this->loadViewsFrom($path.'/Resources/views', strtolower($this->moduleName()));
        }
    }

    /**
     * Folder modul: dua tingkat di atas file provider (Modules/<Nama>/Providers/X.php).
     */
    protected function modulePath(): string
    {
        return dirname((new ReflectionClass($this))->getFileName(), 2);
    }
}
