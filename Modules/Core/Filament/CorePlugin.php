<?php

namespace Modules\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Core\Filament\Pages\ManageSiteSettings;

class CorePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'core';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ManageSiteSettings::class]);
    }

    public function boot(Panel $panel): void {}
}
