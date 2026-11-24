<?php

namespace Modules\Core\Providers;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function moduleName(): string;

    /**
     * Plugin Filament modul, bila ada panel admin.
     */
    protected function filamentPlugin(): ?Plugin
    {
        return null;
    }

    public function getFilamentPlugin(): ?Plugin
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
            Route::middleware('web')->group($path.'/Routes/web.php');
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
