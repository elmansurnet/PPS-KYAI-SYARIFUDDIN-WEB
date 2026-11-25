<?php

namespace Modules\Pendaftaran\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Pendaftaran\Support\KkFileInspector;
use RuntimeException;

/**
 * Penyimpanan file kartu keluarga: disk privat 'kk' (tanpa URL), nama acak, isi terenkripsi.
 *
 * Format file (versi 1): Crypt::encryptString(json_encode({"v":1,"mime":"image/jpeg","data":"<base64>"})).
 * Enkripsi bawaan Laravel (AES-256-CBC + MAC, kunci APP_KEY): file yang diubah ditolak saat dibaca.
 * Seluruh file dienkripsi di memori; ukuran dibatasi config('pendaftaran.kk_max_kb') (bawaan 2 MB).
 * Path yang dikembalikan relatif terhadap root disk, mis. "2026/11/01jd...xyz.kk"; nama dan ekstensi
 * asli dari pengguna tidak pernah dipakai.
 */
class KkStorage
{
    public const DISK = 'kk';

    private const VERSION = 1;

    public function store(UploadedFile $file): string
    {
        $bytes = (string) file_get_contents($file->getRealPath());

        if (strlen($bytes) > self::maxBytes()) {
            throw new InvalidArgumentException('File KK melebihi batas ukuran.');
        }

        $mime = KkFileInspector::detect($bytes) ?? throw new InvalidArgumentException('Jenis file KK tidak didukung.');

        $path = now()->format('Y/m').'/'.strtolower((string) Str::ulid()).'.kk';
        $envelope = json_encode(['v' => self::VERSION, 'mime' => $mime, 'data' => base64_encode($bytes)], JSON_THROW_ON_ERROR);

        Storage::disk(self::DISK)->put($path, Crypt::encryptString($envelope));

        return $path;
    }

    /** Isi asli file (byte mentah) setelah didekripsi. */
    public function contents(string $path): string
    {
        return $this->read($path)['bytes'];
    }

    /** MIME yang terdeteksi saat file disimpan (image/jpeg, image/png, atau application/pdf). */
    public function mime(string $path): string
    {
        return $this->read($path)['mime'];
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    public static function maxBytes(): int
    {
        return (int) config('pendaftaran.kk_max_kb', 2048) * 1024;
    }

    /** @return array{mime: string, bytes: string} */
    private function read(string $path): array
    {
        $envelope = json_decode(Crypt::decryptString(Storage::disk(self::DISK)->get($path)), true);

        $bytes = is_array($envelope) && is_string($envelope['data'] ?? null) ? base64_decode($envelope['data'], true) : false;

        if (($envelope['v'] ?? null) !== self::VERSION || ! in_array($envelope['mime'] ?? null, KkFileInspector::MIMES, true) || $bytes === false) {
            throw new RuntimeException('Format file KK tidak dikenali.');
        }

        return ['mime' => $envelope['mime'], 'bytes' => $bytes];
    }
}
