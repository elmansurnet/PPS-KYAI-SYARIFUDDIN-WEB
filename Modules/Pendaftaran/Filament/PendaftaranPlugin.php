<?php

namespace Modules\Pendaftaran\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Modules\Pendaftaran\Filament\Resources\JenjangResource;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource;

class PendaftaranPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'pendaftaran';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ApplicantResource::class,
            JenjangResource::class,
            KelasOptionResource::class,
            PendidikanTerakhirResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
