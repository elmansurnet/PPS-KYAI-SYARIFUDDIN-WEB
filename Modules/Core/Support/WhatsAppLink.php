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

        // Awalan panggilan internasional "00" (mis. "0062 812..."): buang sebelum memeriksa kode negara.
        if (str_starts_with($digits, '0062')) {
            $digits = substr($digits, 2);
        }

        // Catatan: "062..." TIDAK dianggap kode negara. Nol di depan adalah awalan trunk lokal dan kode
        // area 062x memang ada (mis. 0627 Subulussalam, 0629 Kutacane), jadi "0627..." menjadi "62627...".
        if (str_starts_with($digits, '62')) {
            // "6208..." (62 + nol trunk yang ikut tertulis): buang nol ekstra.
            return '62'.ltrim(substr($digits, 2), '0');
        }

        return '62'.ltrim($digits, '0');
    }

    /**
     * Nomor untuk dibaca manusia, mis. "0812-3456-7890" menjadi "+62 812-3456-7890".
     */
    public static function display(string $raw): string
    {
        $digits = self::normalizeNumber($raw);

        if ($digits === '') {
            return '';
        }

        $local = substr($digits, 2);
        $groups = array_filter([substr($local, 0, 3), substr($local, 3, 4), substr($local, 7)], 'strlen');

        return '+62 '.implode('-', $groups);
    }

    public static function make(string $number, string $message): string
    {
        return 'https://wa.me/'.self::normalizeNumber($number).'?text='.rawurlencode($message);
    }
}
