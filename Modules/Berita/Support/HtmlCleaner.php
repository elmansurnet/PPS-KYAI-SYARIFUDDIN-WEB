<?php

namespace Modules\Berita\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Pembersih HTML isi berita (symfony/html-sanitizer, daftar izin). Penulis berita berhak lebih
 * rendah daripada admin, jadi HTML mereka dianggap tidak tepercaya: dibersihkan saat disimpan
 * (Post::saving) DAN saat ditampilkan (Post::safeBody).
 *
 * Yang lolos: p, br, strong/b, em/i, u, s, sub, sup, h2-h4 (h1 menjadi h2, h5/h6 menjadi h4), ul/ol/li, blockquote, pre/code,
 * hr, tabel dasar, wrapper/presentasi (div, span, mark, dst) dibuka bungkusnya (teks dipertahankan), dan a[href] (http, https, mailto, tel saja; rel dipaksa noopener noreferrer).
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

        // Hierarki isi: halaman sudah punya h1 sendiri, jadi h1 menjadi h2 dan h5/h6 menjadi h4.
        // Hanya penggantian nama tag; sanitiser tetap penentu akhir.
        $html = (string) preg_replace('~<(/?)h1(?=[\s>/])~i', '<$1h2', $html);
        $html = (string) preg_replace('~<(/?)h[56](?=[\s>/])~i', '<$1h4', $html);

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

        // Pembungkus/presentasi tidak berbahaya: tag dibuang, teks dipertahankan (bukan drop).
        foreach (['div', 'span', 'mark', 'section', 'article', 'header', 'footer', 'main', 'aside', 'center', 'font', 'small', 'big', 'figure', 'figcaption', 'details', 'summary', 'abbr', 'cite', 'del', 'ins', 'kbd', 'samp', 'var', 'dl', 'dt', 'dd', 'address', 'nav', 'label', 'colgroup', 'col'] as $element) {
            $config = $config->blockElement($element);
        }

        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->dropElement('script')
            ->dropElement('textarea')
            ->dropElement('select')
            ->dropElement('button')
            ->dropElement('input')
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
