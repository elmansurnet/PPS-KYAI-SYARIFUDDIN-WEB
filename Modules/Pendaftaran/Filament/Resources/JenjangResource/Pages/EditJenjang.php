<?php

namespace Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pendaftaran\Filament\Resources\JenjangResource;

class EditJenjang extends EditRecord
{
    protected static string $resource = JenjangResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->kelasOptions()->exists() || $this->getRecord()->applicants()->exists())];
    }
}
