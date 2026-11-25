<?php

namespace Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\ApplicantExportActions;

class ListApplicants extends ListRecords
{
    protected static string $resource = ApplicantResource::class;

    protected ?string $subheading = 'Pencarian NIK: ketik 16 digit lengkap (NIK sebagian tidak bisa dicari karena terenkripsi). Ekspor mengikuti saringan dan pencarian aktif.';

    protected function getHeaderActions(): array
    {
        return [
            ApplicantExportActions::excel(),
            ApplicantExportActions::pdf(),
        ];
    }
}
