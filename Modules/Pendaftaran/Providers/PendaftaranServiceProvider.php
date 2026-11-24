<?php

namespace Modules\Pendaftaran\Providers;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Providers\ModuleServiceProvider;
use Modules\Pendaftaran\Console\RehashNikCommand;
use Modules\Pendaftaran\Filament\PendaftaranPlugin;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;
use Modules\Pendaftaran\Policies\JenjangPolicy;
use Modules\Pendaftaran\Policies\KelasOptionPolicy;
use Modules\Pendaftaran\Policies\PendidikanTerakhirPolicy;

class PendaftaranServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Pendaftaran';
    }

    protected function filamentPlugin(): ?Plugin
    {
        return PendaftaranPlugin::make();
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->modulePath().'/Config/pendaftaran.php', 'pendaftaran');
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Jenjang::class, JenjangPolicy::class);
        Gate::policy(KelasOption::class, KelasOptionPolicy::class);
        Gate::policy(PendidikanTerakhir::class, PendidikanTerakhirPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RehashNikCommand::class]);
        }
    }
}
