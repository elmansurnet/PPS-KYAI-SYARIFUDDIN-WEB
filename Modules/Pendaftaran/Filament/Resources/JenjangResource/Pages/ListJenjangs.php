<?php

namespace Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pendaftaran\Filament\Resources\JenjangResource;

class ListJenjangs extends ListRecords
{
    protected static string $resource = JenjangResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
