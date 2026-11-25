<?php

namespace Modules\Pendaftaran\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Support\NikDisplay;

/**
 * Susunan kolom ekspor pendaftar (dipakai Excel dan PDF) dan aturan nilai selnya.
 *
 * Injeksi formula (CSV/Excel injection): teks isian pendaftar yang diawali =, +, -, @, tab, atau CR
 * diberi awalan apostrof, sehingga tetap teks walau lembar disimpan ulang sebagai CSV. Excel juga
 * menulis semua sel sebagai tipe string (lihat ApplicantsExport::bindValue).
 */
class ApplicantRow
{
    public const HEADINGS = [
        'No', 'Nama', 'NIK', 'TTL', 'Alamat lengkap', 'Pernah mondok', 'Pondok sebelumnya', 'Pendidikan terakhir',
        'Jenjang', 'Kelas', 'Nama ayah', 'HP ayah', 'Nama ibu', 'HP ibu', 'Wali', 'Sudah dihubungi', 'Sudah bayar',
        'Sudah masuk', 'Tanggal daftar',
    ];

    public const EMPTY = '-';

    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r", "\n"];

    /**
     * Urutan stabil untuk pembacaan bertahap (chunk memakai LIMIT/OFFSET): urutan yang sudah ada
     * (mis. dari tabel Filament) dipertahankan dan id ditambahkan sebagai pemecah seri.
     */
    public static function prepare(Builder $query): Builder
    {
        $query = (clone $query)->with(['jenjang', 'pendidikanTerakhir']);
        $model = $query->getModel();

        if (empty($query->getQuery()->orders)) {
            $query->orderByDesc($model->qualifyColumn('created_at'));
        }

        return $query->orderByDesc($model->qualifyColumn($model->getKeyName()));
    }

    /**
     * @return list<int|string>
     */
    public static function values(Applicant $applicant, int $number, bool $revealNik): array
    {
        $flag = fn (bool $value): string => $value ? 'Ya' : 'Belum';

        $wali = $applicant->orang_tua_meninggal && filled($applicant->wali_nama)
            ? self::clean($applicant->wali_nama).(filled($applicant->wali_hp) ? ' ('.self::clean($applicant->wali_hp).')' : '')
            : self::EMPTY;

        $pondok = self::EMPTY;
        if ($applicant->pernah_mondok && filled($applicant->pondok_sebelumnya)) {
            $pondok = self::clean($applicant->pondok_sebelumnya)
                .(filled($applicant->pendidikan_pondok_sebelumnya) ? ' ('.self::clean($applicant->pendidikan_pondok_sebelumnya).')' : '');
        }

        return [
            $number,
            self::text($applicant->nama_lengkap),
            NikDisplay::for($applicant, $revealNik),
            self::text($applicant->tempat_lahir).', '.($applicant->tanggal_lahir?->format('d-m-Y') ?? self::EMPTY),
            self::address($applicant),
            $applicant->pernah_mondok ? 'Ya' : 'Tidak',
            $pondok,
            self::text($applicant->pendidikanTerakhir?->name),
            self::text($applicant->jenjang?->name),
            self::text($applicant->kelas),
            self::text($applicant->ayah_nama),
            self::text($applicant->ayah_hp),
            self::text($applicant->ibu_nama),
            self::text($applicant->ibu_hp),
            $wali,
            $flag((bool) $applicant->sudah_dihubungi),
            $flag((bool) $applicant->sudah_bayar),
            $flag((bool) $applicant->sudah_masuk),
            $applicant->created_at instanceof Carbon ? $applicant->created_at->format('d-m-Y H:i') : self::EMPTY,
        ];
    }

    /** Alamat satu baris. $prefix "ortu_"/"wali_" untuk alamat orang tua/wali; $neutralise=false untuk tampilan HTML. */
    public static function address(Applicant $applicant, string $prefix = '', bool $neutralise = true): string
    {
        $get = fn (string $field) => $applicant->getAttribute($prefix.$field);

        $rtRw = array_filter([
            filled($get('rt')) ? 'RT '.$get('rt') : null,
            filled($get('rw')) ? 'RW '.$get('rw') : null,
        ]);

        $parts = array_filter([
            $get('jalan_dusun'),
            $rtRw ? implode('/', $rtRw) : null,
            $get('desa'),
            $get('kecamatan'),
            $get('kabupaten'),
            $get('provinsi'),
        ], fn ($part) => filled($part));

        if (! $parts) {
            return self::EMPTY;
        }

        $line = implode(', ', $parts);

        return $neutralise ? self::clean($line) : $line;
    }

    /** Teks isian (atau penanda kosong) yang aman dari injeksi formula. */
    public static function text(mixed $value): string
    {
        return filled($value) ? self::clean((string) $value) : self::EMPTY;
    }

    public static function clean(string $value): string
    {
        // Pemicu formula juga dicek setelah spasi/tab/CR/LF di depan dibuang (" =cmd", "\n=cmd");
        // nilai yang diawali tab/CR/LF selalu dinetralkan.
        $trimmed = ltrim($value, " \t\r\n");

        if (($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true))
            || ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true))) {
            return "'".$value;
        }

        return $value;
    }
}
