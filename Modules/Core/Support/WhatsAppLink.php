<?php

namespace Modules\Core\Support;

class WhatsAppLink
{
    /**
     * Ubah nomor bebas format menjadi digit berawalan 62 (tanpa plus, spasi, atau nol awal).
     * Masukan tanpa angka menghasilkan string kosong.
     */
    public static function normalizeNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            // "6208..." (62 + nol trunk yang ikut tertulis): buang nol ekstra.
            return '62'.ltrim(substr($digits, 2), '0');
        }

        return '62'.ltrim($digits, '0');
    }

    public static function make(string $number, string $message): string
    {
        return 'https://wa.me/'.self::normalizeNumber($number).'?text='.rawurlencode($message);
    }
}
