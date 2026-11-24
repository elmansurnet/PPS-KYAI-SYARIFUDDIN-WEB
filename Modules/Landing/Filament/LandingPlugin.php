<?php

namespace Modules\Landing\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Landing\Filament\Resources\LandingSectionResource;

class LandingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'landing';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([LandingSectionResource::class]);
    }

    public function boot(Panel $panel): void {}
}
