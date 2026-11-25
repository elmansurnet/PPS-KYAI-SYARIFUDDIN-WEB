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
     * Penanda konten aktif PDF (JavaScript, aksi otomatis, lampiran, media, formulir XFA). Dicari pada byte mentah,
     * peka huruf besar/kecil; bentuk yang dikaburkan (hex/objek terkompresi) bisa lolos, jadi ini pertahanan
     * berlapis: unduhan tetap lampiran + nosniff + CSP sandbox.
     */
    public const PDF_ACTIVE_TOKENS = ['/JavaScript', '/JS', '/Launch', '/OpenAction', '/AA', '/EmbeddedFile', '/RichMedia', '/XFA'];

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
        foreach (self::PDF_ACTIVE_TOKENS as $token) {
            if (str_contains($bytes, $token)) {
                return true;
            }
        }

        return false;
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
