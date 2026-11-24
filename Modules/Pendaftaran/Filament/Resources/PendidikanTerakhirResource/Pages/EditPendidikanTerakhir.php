<?php

namespace Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource;

class EditPendidikanTerakhir extends EditRecord
{
    protected static string $resource = PendidikanTerakhirResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->applicants()->exists())];
    }
}
