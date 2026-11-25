<?php

namespace Modules\Pendaftaran\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Settings\SiteSettings;
use Modules\Pendaftaran\Models\Applicant;
use Throwable;

/**
 * Ekspor PDF pendaftar: A4 lanskap, judul (nama situs, tanggal ekspor, ringkasan saringan, jumlah).
 *
 * - Paling banyak config('pendaftaran.pdf_max_rows') baris (dompdf menyusun semuanya di memori). Aksi
 *   Filament menolak lebih dulu bila hasil saringan melebihi batas; di sini batas tetap dipaksakan dan
 *   PDF memuat catatan bila terpotong.
 * - NIK lengkap hanya dengan izin RevealNik; selain itu tersamar.
 * - Semua nilai di-escape Blade ({{ }}); dompdf tanpa sumber remote dan tanpa PHP.
 * Pencatatan audit dilakukan pemanggil.
 */
class ApplicantsPdf
{
    public static function maxRows(): int
    {
        return max(1, (int) config('pendaftaran.pdf_max_rows', 500));
    }

    /**
     * @param  list<string>  $filters  ringkasan saringan/pencarian yang aktif (teks polos, di-escape)
     */
    public static function render(Builder $query, array $filters = [], ?bool $revealNik = null): string
    {
        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ])
            ->loadHTML(self::html($query, $filters, $revealNik))
            ->setPaper('a4', 'landscape')
            ->output();
    }

    /**
     * @param  list<string>  $filters
     */
    public static function html(Builder $query, array $filters = [], ?bool $revealNik = null): string
    {
        $revealNik ??= (bool) auth()->user()?->can('revealNik', Applicant::class);
        $max = self::maxRows();

        $prepared = ApplicantRow::prepare($query);
        $total = (clone $prepared)->count();

        $rows = [];
        $number = 0;
        foreach ($prepared->limit($max)->lazy(ApplicantsExport::CHUNK) as $applicant) {
            $rows[] = ApplicantRow::values($applicant, ++$number, $revealNik);
        }

        return view('pendaftaran::admin.pendaftar-pdf', [
            'siteName' => self::siteName(),
            'exportedAt' => now()->format('d-m-Y H:i'),
            'filters' => $filters,
            'total' => $total,
            'rows' => $rows,
            'truncated' => $total > $max,
            'max' => $max,
            'revealNik' => $revealNik,
        ])->render();
    }

    private static function siteName(): string
    {
        try {
            return (string) app(SiteSettings::class)->site_name;
        } catch (Throwable) {
            return (string) config('app.name');
        }
    }
}
