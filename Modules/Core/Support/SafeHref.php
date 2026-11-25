<?php

namespace Modules\Core\Support;

/**
 * Daftar izin untuk nilai href tautan publik. Hanya path relatif situs ("/..." tetapi bukan
 * "//" atau "/\"), anchor "#...", http(s), mailto:, dan tel: yang lolos; selain itu
 * (javascript:, data:, vbscript:, path relatif polos, dsb.) diganti "#".
 */
class SafeHref
{
    public static function sanitize(?string $href): string
    {
        // Peramban membuang tab, baris baru, dan karakter kontrol saat mengurai URL: "/<TAB>/evil.com" menjadi "//evil.com".
        $href = preg_replace('/[\x00-\x20]/', '', (string) $href) ?? '';

        if (preg_match('~^(/(?![/\\\\])|#|https?://[^/\\\\\s]|mailto:|tel:)~i', $href) !== 1) {
            return '#';
        }

        return $href;
    }
}
