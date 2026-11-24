<?php

namespace Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource;

class ListKelasOptions extends ListRecords
{
    protected static string $resource = KelasOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
