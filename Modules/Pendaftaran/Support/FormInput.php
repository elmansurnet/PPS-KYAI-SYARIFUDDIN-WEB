<?php

namespace Modules\Pendaftaran\Support;

/**
 * Normalisasi isian formulir pendaftaran sebelum validasi. Tidak memvalidasi; nilai aneh dibiarkan
 * agar aturan validasi yang menolaknya dengan pesan yang jelas.
 */
class FormInput
{
    /** Hanya string skalar; spasi/tab/baris baru dirapatkan menjadi satu spasi, ujung dipangkas. */
    public static function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $clean = preg_replace('/\s+/u', ' ', $value);

        // UTF-8 rusak: biarkan apa adanya supaya ditolak aturan PlainText.
        $clean = trim($clean ?? $value);

        return $clean === '' ? null : $clean;
    }

    /** Nama: seperti text(), apostrof lengkung diganti apostrof biasa. */
    public static function name(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : str_replace(["\u{2019}", "\u{2018}", "\u{02BC}", '`'], "'", $text);
    }

    /** NIK: spasi, titik, dan tanda hubung dibuang (huruf dibiarkan agar ditolak validasi). */
    public static function nik(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : preg_replace('/[\s.\-]+/', '', $text);
    }

    /** Nomor HP: spasi, tanda hubung, titik, dan kurung dibuang; "+" di depan dipertahankan untuk validasi. */
    public static function phone(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $clean = preg_replace('/[\s\-.()]+/', '', $text) ?? $text;

        return $clean === '' ? null : $clean;
    }

    /** Nomor HP yang sudah sah disimpan seragam dalam format lokal 08xxxxxxxxxx. */
    public static function storedPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = ltrim($phone, '+');

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }

    /**
     * Tanggal: "17-08-2014", "17/08/2014", atau "17.08.2014" (peramban tanpa pemilih tanggal) diubah
     * ke "2014-08-17"; format lain dibiarkan untuk divalidasi date_format:Y-m-d.
     */
    public static function date(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text !== null && preg_match('~^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$~', $text, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $text;
    }

    /** RT/RW sah (1-3 digit) disimpan 3 digit seperti di KK, mis. "1" menjadi "001". */
    public static function storedRtRw(?string $value): ?string
    {
        return $value === null ? null : str_pad($value, 3, '0', STR_PAD_LEFT);
    }
}
