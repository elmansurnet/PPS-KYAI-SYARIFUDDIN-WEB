<?php

namespace Modules\Berita\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Berita\Filament\Resources\CategoryResource;
use Modules\Berita\Filament\Resources\PostResource;

class BeritaPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'berita';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([PostResource::class, CategoryResource::class]);
    }

    public function boot(Panel $panel): void {}
}
