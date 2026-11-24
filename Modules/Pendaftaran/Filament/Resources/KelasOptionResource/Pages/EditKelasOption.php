<?php

namespace Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource;

class EditKelasOption extends EditRecord
{
    protected static string $resource = KelasOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
