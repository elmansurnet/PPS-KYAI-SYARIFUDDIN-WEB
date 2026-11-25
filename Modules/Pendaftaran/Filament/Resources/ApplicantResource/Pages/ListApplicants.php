<?php

namespace Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\ApplicantExportActions;

class ListApplicants extends ListRecords
{
    protected static string $resource = ApplicantResource::class;

    /**
     * Dideklarasikan ulang TANPA #[Url] (induk ListRecords memakai #[Url(as: 'search')]): kata kunci bisa
     * berupa NIK lengkap dan tidak boleh masuk ke URL, riwayat peramban, log akses, atau header Referer.
     * Pencarian juga tidak disimpan di sesi (persistSearchInSession tidak dipakai).
     *
     * @var ?string
     */
    public $tableSearch = '';

    protected ?string $subheading = 'Pencarian NIK: ketik 16 digit lengkap (NIK sebagian tidak bisa dicari karena terenkripsi). Ekspor mengikuti saringan dan pencarian aktif.';

    protected function getHeaderActions(): array
    {
        return [
            ApplicantExportActions::excel(),
            ApplicantExportActions::pdf(),
        ];
    }
}
