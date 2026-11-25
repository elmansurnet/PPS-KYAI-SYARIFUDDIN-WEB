<?php

namespace Modules\Pendaftaran\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian tabel pendaftar.
 *
 * - Bila kata kunci (setelah spasi/titik/tanda hubung dibuang) tepat 16 digit: cocokkan nik_hash
 *   (HMAC) saja. NIK terenkripsi sehingga pencarian sebagian NIK tidak mungkin; kolom nik TIDAK
 *   PERNAH dipakai di LIKE.
 * - Bila kata kunci hanya angka + pemisah (4-15 digit): cari di nomor HP ayah/ibu/wali (digit saja,
 *   karena HP disimpan "08...").
 * - Selain itu: tiap kata harus muncul (AND) di salah satu kolom teks (OR). Wildcard LIKE (% dan _)
 *   di-escape dengan karakter "!" (ESCAPE '!' portabel antara SQLite dan MySQL, tidak bergantung
 *   pada mode backslash MySQL).
 */
class ApplicantSearch
{
    /** Kolom teks yang boleh dicari (nama kolom tetap, tidak pernah dari input). */
    public const TEXT_COLUMNS = [
        'nama_lengkap', 'ayah_nama', 'ibu_nama', 'wali_nama',
        'ayah_hp', 'ibu_hp', 'wali_hp',
        'desa', 'kecamatan', 'kabupaten',
    ];

    public const PHONE_COLUMNS = ['ayah_hp', 'ibu_hp', 'wali_hp'];

    public static function apply(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $model = $query->getModel();

        if (preg_match('/^[\d\s.\-]+$/', $search) === 1) {
            $digits = NikHasher::normalize($search);

            if (strlen($digits) === 16) {
                return $query->where($model->qualifyColumn('nik_hash'), NikHasher::hash($digits));
            }

            if (strlen($digits) >= 4 && strlen($digits) <= 15) {
                return $query->where(function (Builder $q) use ($model, $digits) {
                    foreach (self::PHONE_COLUMNS as $column) {
                        self::orLike($q, $model->qualifyColumn($column), $digits);
                    }
                });
            }
        }

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->where(function (Builder $q) use ($model, $word) {
                foreach (self::TEXT_COLUMNS as $column) {
                    self::orLike($q, $model->qualifyColumn($column), $word);
                }
            });
        }

        return $query;
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private static function orLike(Builder $query, string $column, string $value): void
    {
        $grammar = $query->getQuery()->getGrammar();

        $query->orWhereRaw($grammar->wrap($column)." LIKE ? ESCAPE '!'", ['%'.self::escapeLike($value).'%']);
    }
}
