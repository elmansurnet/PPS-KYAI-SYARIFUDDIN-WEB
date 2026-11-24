<?php

namespace Modules\Pendaftaran\Support;

use RuntimeException;

/**
 * Hash NIK untuk cek duplikat dan pencarian (HMAC-SHA256, hex).
 *
 * Kunci: config('pendaftaran.nik_hmac_key') dari env NIK_HMAC_KEY. Gagal tertutup: tanpa kunci
 * melempar RuntimeException, tidak pernah memakai APP_KEY atau nilai bawaan.
 *
 * Mengganti kunci membuat semua nik_hash lama tidak cocok (cek duplikat dan pencarian rusak)
 * sampai `php artisan pendaftaran:rehash-nik` dijalankan.
 */
class NikHasher
{
    /** Panjang minimum kunci (karakter). Ruang NIK kecil, jadi kunci lemah membuat hash bisa ditebak offline. */
    public const MIN_KEY_LENGTH = 32;

    public static function hash(string $nik): string
    {
        $key = config('pendaftaran.nik_hmac_key');

        if (! is_string($key) || strlen(trim($key)) < self::MIN_KEY_LENGTH) {
            throw new RuntimeException(
                'NIK_HMAC_KEY belum diisi atau terlalu pendek (minimal 32 karakter acak, disarankan 64 karakter hex). '
                .'Buat dengan: php -r "echo bin2hex(random_bytes(32));" lalu isi di .env '
                .'(cadangkan terpisah dari APP_KEY, jangan pakai ulang APP_KEY).'
            );
        }

        return hash_hmac('sha256', self::normalize($nik), $key);
    }

    /**
     * Hanya digit: spasi, titik, dan tanda hubung dibuang.
     */
    public static function normalize(string $nik): string
    {
        return preg_replace('/\D+/', '', $nik) ?? '';
    }
}
