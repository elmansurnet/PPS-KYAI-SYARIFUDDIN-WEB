<?php

namespace Modules\Pendaftaran\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pendaftaran\Models\Applicant;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor Excel pendaftar dari query apa pun (biasanya query tabel Filament yang sudah tersaring dan
 * tercari). Dibaca bertahap per CHUNK baris, tanpa batas jumlah.
 *
 * - NIK lengkap hanya bila pengekspor punya izin RevealNik; selain itu tersamar.
 * - Semua sel ditulis sebagai string (kecuali nomor urut), jadi tidak ada sel yang menjadi formula, dan
 *   NIK/HP tidak kehilangan angka 0 atau presisi. Kolom NIK dan HP juga diformat teks ("@").
 * - Teks berawalan karakter formula diberi apostrof (ApplicantRow::clean).
 * Pencatatan audit dilakukan pemanggil (aksi Filament), bukan di sini.
 */
class ApplicantsExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithCustomChunkSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public const CHUNK = 200;

    private int $number = 0;

    private function __construct(private readonly Builder $query, private readonly bool $revealNik) {}

    public static function fromQuery(Builder $query, ?bool $revealNik = null): self
    {
        $revealNik ??= (bool) auth()->user()?->can('revealNik', Applicant::class);

        return new self(ApplicantRow::prepare($query), $revealNik);
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ApplicantRow::HEADINGS;
    }

    /** @param  Applicant  $row */
    public function map(mixed $row): array
    {
        return ApplicantRow::values($row, ++$this->number, $this->revealNik);
    }

    public function chunkSize(): int
    {
        return self::CHUNK;
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_int($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);

            return true;
        }

        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function toBinary(): string
    {
        return Excel::raw($this, ExcelWriter::XLSX);
    }

    public function download(string $fileName): BinaryFileResponse
    {
        return Excel::download($this, $fileName, ExcelWriter::XLSX, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
