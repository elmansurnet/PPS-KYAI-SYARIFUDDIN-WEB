<?php

namespace Modules\Pendaftaran\Filament\Resources\ApplicantResource;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Support\AuditLog;
use Modules\Pendaftaran\Exports\ApplicantsExport;
use Modules\Pendaftaran\Exports\ApplicantsPdf;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Support\NikHasher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aksi ekspor Excel/PDF. Versi header memakai query tabel yang SAMA dengan yang tampil (saringan +
 * pencarian + urutan, semua halaman); versi massal hanya rekaman terpilih. Semua butuh izin export
 * (ApplicantPolicy) dan dicatat AuditLog "pendaftaran.ekspor" {format, count} tanpa data pribadi.
 */
class ApplicantExportActions
{
    public static function excel(): Action
    {
        return Action::make('eksporExcel')
            ->label('Ekspor Excel')
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('export', Applicant::class))
            ->action(fn (HasTable $livewire) => self::downloadExcel($livewire->getFilteredSortedTableQuery()));
    }

    public static function pdf(): Action
    {
        return Action::make('eksporPdf')
            ->label('Ekspor PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('export', Applicant::class))
            ->action(fn (HasTable $livewire) => self::downloadPdf(
                $livewire->getFilteredSortedTableQuery(),
                self::filterSummary($livewire),
            ));
    }

    public static function excelBulk(): BulkAction
    {
        return BulkAction::make('eksporExcelTerpilih')
            ->label('Ekspor Excel (terpilih)')
            ->icon('heroicon-o-table-cells')
            ->authorize(fn (): bool => Gate::allows('export', Applicant::class))
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::downloadExcel(self::selected($records)));
    }

    public static function pdfBulk(): BulkAction
    {
        return BulkAction::make('eksporPdfTerpilih')
            ->label('Ekspor PDF (terpilih)')
            ->icon('heroicon-o-document-arrow-down')
            ->authorize(fn (): bool => Gate::allows('export', Applicant::class))
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::downloadPdf(self::selected($records), ['Rekaman terpilih']));
    }

    private static function downloadExcel(Builder $query): Response
    {
        $count = (clone $query)->count();
        // Audit DULU (gagal tertutup): bila pencatatan gagal, ekspor batal sebelum file sementara xlsx berisi
        // data pribadi dibuat. File sementara dihapus maatwebsite setelah dikirim (deleteFileAfterSend).
        AuditLog::record('pendaftaran.ekspor', null, ['format' => 'xlsx', 'count' => $count]);

        return ApplicantsExport::fromQuery($query)->download(self::fileName('xlsx'));
    }

    /**
     * @param  list<string>  $filters
     */
    private static function downloadPdf(Builder $query, array $filters): ?Response
    {
        $count = (clone $query)->count();
        $max = ApplicantsPdf::maxRows();

        if ($count > $max) {
            Notification::make()
                ->danger()
                ->title('Terlalu banyak data untuk PDF')
                ->body("Hasil saringan berisi {$count} pendaftar, batas PDF {$max}. Persempit saringan atau gunakan Ekspor Excel.")
                ->send();

            return null;
        }

        AuditLog::record('pendaftaran.ekspor', null, ['format' => 'pdf', 'count' => $count]);

        $pdf = ApplicantsPdf::render($query, $filters);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, self::fileName('pdf'), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function selected(Collection $records): Builder
    {
        return Applicant::query()->whereKey($records->modelKeys())->latest()->orderByDesc('id');
    }

    /**
     * Ringkasan saringan untuk judul PDF. Kata kunci NIK tidak ditulis ulang (bisa dibaca siapa pun
     * yang menerima PDF), cukup keterangan bahwa pencarian NIK dipakai.
     *
     * @return list<string>
     */
    private static function filterSummary(HasTable $livewire): array
    {
        $summary = [];
        $search = trim((string) $livewire->getTableSearch());

        if ($search !== '') {
            $summary[] = strlen(NikHasher::normalize($search)) === 16 && preg_match('/^[\d\s.\-]+$/', $search) === 1
                ? 'Pencarian: NIK (16 digit)'
                : 'Pencarian: '.mb_strimwidth($search, 0, 60, '...');
        }

        foreach ($livewire->getTable()->getFilters() as $filter) {
            /** @var BaseFilter $filter */
            foreach ($filter->getIndicators() as $indicator) {
                /** @var Indicator $indicator */
                $summary[] = strip_tags((string) $indicator->getLabel());
            }
        }

        return $summary;
    }

    private static function fileName(string $extension): string
    {
        return 'pendaftar-'.now()->format('Ymd-His').'.'.$extension;
    }
}
