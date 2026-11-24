<?php

namespace Modules\Berita\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Pembersih HTML isi berita (symfony/html-sanitizer, daftar izin). Penulis berita berhak lebih
 * rendah daripada admin, jadi HTML mereka dianggap tidak tepercaya: dibersihkan saat disimpan
 * (Post::saving) DAN saat ditampilkan (Post::safeBody).
 *
 * Yang lolos: p, br, strong/b, em/i, u, s, sub, sup, h2-h4, ul/ol/li, blockquote, pre/code,
 * hr, tabel dasar, dan a[href] (http, https, mailto, tel saja; rel dipaksa noopener noreferrer).
 * Tanpa atribut lain (class dan style dibuang demi CSP), tanpa gambar (sampul dikelola terpisah
 * dan lampiran editor dimatikan), tanpa iframe/form/svg/script/style.
 */
final class HtmlCleaner
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        return self::sanitizer()->sanitizeFor('body', $html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer) {
            return self::$sanitizer;
        }

        $config = new HtmlSanitizerConfig;

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('svg')
            ->dropElement('math')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('form')
            ->dropElement('template')
            ->dropElement('noscript')
            ->withMaxInputLength(500000);

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
