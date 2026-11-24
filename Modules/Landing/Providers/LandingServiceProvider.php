<?php

namespace Modules\Landing\Providers;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Providers\ModuleServiceProvider;
use Modules\Landing\Filament\LandingPlugin;
use Modules\Landing\Models\LandingSection;
use Modules\Landing\Policies\LandingSectionPolicy;

class LandingServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Landing';
    }

    protected function filamentPlugin(): ?Plugin
    {
        return LandingPlugin::make();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(LandingSection::class, LandingSectionPolicy::class);
    }
}
