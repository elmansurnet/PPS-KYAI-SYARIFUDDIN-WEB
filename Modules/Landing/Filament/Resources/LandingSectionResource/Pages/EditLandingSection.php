<?php

namespace Modules\Landing\Filament\Resources\LandingSectionResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Landing\Filament\Resources\LandingSectionResource;

class EditLandingSection extends EditRecord
{
    protected static string $resource = LandingSectionResource::class;

    // Bagian beranda tidak bisa dihapus: tanpa aksi header.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
