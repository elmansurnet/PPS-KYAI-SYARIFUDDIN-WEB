<?php

namespace Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource;

class ListPendidikanTerakhirs extends ListRecords
{
    protected static string $resource = PendidikanTerakhirResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
