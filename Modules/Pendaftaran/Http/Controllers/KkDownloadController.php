<?php

namespace Modules\Pendaftaran\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Core\Http\Middleware\SecurityHeaders;
use Modules\Core\Support\AuditLog;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Services\KkStorage;
use Throwable;

/**
 * Unduh file KK (route pendaftar.kk). Login panel + izin downloadKk dicek middleware route.
 *
 * - Nama file tetap "kk-<id>.<ext>" (nama asli tidak pernah dipakai), MIME dari amplop terenkripsi.
 * - Header: attachment, nosniff, CSP sandbox (PDF/gambar tidak bisa menjalankan skrip di origin ini),
 *   no-store.
 * - File hilang/rusak/tidak bisa didekripsi: 404 polos tanpa path atau detail pengecualian; log hanya
 *   id pendaftar + nama kelas pengecualian.
 */
class KkDownloadController
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function __invoke(Request $request, Applicant $applicant, KkStorage $storage): Response
    {
        if (blank($applicant->kk_path)) {
            return $this->unavailable();
        }

        try {
            $bytes = $storage->contents($applicant->kk_path);
            $mime = $storage->mime($applicant->kk_path);
        } catch (Throwable $e) {
            Log::warning('pendaftaran: file KK tidak bisa dibaca', [
                'applicant_id' => $applicant->getKey(),
                'exception' => $e::class,
            ]);

            return $this->unavailable();
        }

        $extension = self::EXTENSIONS[$mime] ?? 'bin';

        AuditLog::record('pendaftaran.kk_diunduh', $applicant);

        // Minta SecurityHeaders mempertahankan CSP sandbox di bawah (hanya berlaku bila nilainya diawali "sandbox").
        $request->attributes->set(SecurityHeaders::KEEP_SANDBOX_CSP, true);

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'attachment; filename="kk-'.(int) $applicant->getKey().'.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function unavailable(): Response
    {
        return response('File KK tidak tersedia.', 404, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
