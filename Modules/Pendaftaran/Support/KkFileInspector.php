<?php

namespace Modules\Pendaftaran\Support;

use finfo;

/**
 * Pemeriksaan isi file kartu keluarga (bukan ekstensi atau MIME kiriman peramban).
 *
 * Diterima hanya JPEG, PNG, dan PDF yang isinya cocok:
 * - MIME hasil finfo dari isi file;
 * - JPEG/PNG: tanda tangan biner di awal file dan getimagesize() mengenali jenis yang sama;
 * - PDF: diawali "%PDF-" dan memuat "%%EOF" di 1 KB terakhir.
 * PDF yang memuat penanda konten aktif (PDF_ACTIVE_TOKENS) ditolak.
 * Penanda kode yang lazim pada file poliglot (<?php, <script, <html, <!doctype html) ditolak.
 * File tetap dienkripsi saat disimpan dan diunduh sebagai lampiran, jadi ini lapisan pertahanan tambahan.
 */
class KkFileInspector
{
    public const MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    /**
     * Nama PDF yang menandakan konten aktif (JavaScript, aksi otomatis, lampiran, media, formulir XFA). Dicocokkan
     * sebagai NAMA utuh (diikuti pembatas) pada byte mentah di luar badan stream, peka huruf besar/kecil; bentuk yang
     * dikaburkan (hex, objek terkompresi) bisa lolos, jadi ini pertahanan berlapis: unduhan tetap lampiran + nosniff
     * + CSP sandbox.
     */
    public const PDF_ACTIVE_PATTERN = '~/(?:JavaScript|JS|Launch|OpenAction|AA|EmbeddedFiles?|RichMedia|XFA)(?=[\s/<>\[\]()%{}]|$)~';

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    /**
     * MIME yang terdeteksi bila file sah, atau null bila ditolak.
     */
    public static function detect(string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, self::MIMES, true)) {
            return null;
        }

        $valid = match ($mime) {
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF") && self::imageType($bytes) === IMAGETYPE_JPEG,
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n") && self::imageType($bytes) === IMAGETYPE_PNG,
            'application/pdf' => str_starts_with($bytes, '%PDF-') && str_contains(substr($bytes, -1024), '%%EOF'),
        };

        if ($valid && $mime === 'application/pdf' && self::hasActiveContent($bytes)) {
            return null;
        }

        if (! $valid || preg_match('/<\?php|<script|<html|<!doctype\s+html/i', $bytes) === 1) {
            return null;
        }

        return $mime;
    }

    private static function hasActiveContent(string $bytes): bool
    {
        return preg_match(self::PDF_ACTIVE_PATTERN, self::withoutStreamBodies($bytes)) === 1;
    }

    /**
     * Buang isi "stream ... endstream" (biner acak bisa kebetulan memuat "/JS"). Tanpa regex agar aman untuk file 5 MB.
     * Stream yang tak ditutup tidak dibuang (tetap dipindai).
     */
    private static function withoutStreamBodies(string $bytes): string
    {
        $out = '';
        $pos = 0;
        $len = strlen($bytes);

        while (($start = strpos($bytes, 'stream', $pos)) !== false) {
            if ($start >= 3 && substr($bytes, $start - 3, 3) === 'end') {
                $out .= substr($bytes, $pos, $start + 6 - $pos);
                $pos = $start + 6;

                continue;
            }

            $end = strpos($bytes, 'endstream', $start + 6);

            if ($end === false) {
                $out .= substr($bytes, $pos, $start + 6 - $pos);
                $pos = $start + 6;

                continue;
            }

            $out .= substr($bytes, $pos, $start + 6 - $pos);
            $pos = $end;
        }

        return $out.substr($bytes, min($pos, $len));
    }
    private static function imageType(string $bytes): ?int
    {
        $info = @getimagesizefromstring($bytes);

        if (! is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return null;
        }

        return $info[2] ?? null;
    }
}
