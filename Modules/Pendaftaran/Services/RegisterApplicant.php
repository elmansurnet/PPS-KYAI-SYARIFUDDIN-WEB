<?php

namespace Modules\Pendaftaran\Services;

use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\AuditLog;
use Modules\Pendaftaran\Exceptions\DuplicateNikException;
use Modules\Pendaftaran\Exceptions\RegistrationFailedException;
use Modules\Pendaftaran\Models\Applicant;
use Throwable;

/**
 * Menyimpan pendaftar baru beserta file KK sebagai satu kesatuan.
 *
 * Urutan: cek duplikat (findByNik) -> simpan file KK terenkripsi -> transaksi DB (buat pendaftar,
 * catat audit). Gagal menyimpan file: tidak ada baris pendaftar. Gagal di transaksi: file KK dihapus
 * lagi (tanpa file yatim). Pelanggaran unik nik_hash dari permintaan yang balapan diubah menjadi
 * DuplicateNikException. Pesan pengecualian asli TIDAK pernah dicatat (QueryException memuat semua
 * binding = data pribadi); log hanya berisi pesan tetap dan nama kelas pengecualian.
 */
class RegisterApplicant
{
    public function __construct(private readonly KkStorage $kk) {}

    /**
     * @param  array<string, mixed>  $data  atribut Applicant yang sudah divalidasi dan dinormalisasi
     *
     * @throws DuplicateNikException
     * @throws RegistrationFailedException
     */
    public function handle(array $data, UploadedFile $kk): Applicant
    {
        if (Applicant::findByNik((string) $data['nik']) !== null) {
            throw new DuplicateNikException;
        }

        try {
            $path = $this->kk->store($kk);
        } catch (Throwable $e) {
            Log::error('Pendaftaran: file KK gagal disimpan.', ['exception_class' => $e::class]);

            throw new RegistrationFailedException;
        }

        try {
            return DB::transaction(function () use ($data, $path) {
                $applicant = new Applicant;
                $applicant->fill($data);
                $applicant->forceFill([
                    'kk_path' => $path,
                    'sudah_dihubungi' => false,
                    'sudah_bayar' => false,
                    'sudah_masuk' => false,
                ]);
                $applicant->save();

                AuditLog::record('pendaftaran.baru', $applicant, ['applicant_id' => $applicant->id]);

                return $applicant;
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            if ($e instanceof QueryException && Applicant::isDuplicateNikViolation($e)) {
                throw new DuplicateNikException;
            }

            Log::error('Pendaftaran: data pendaftar gagal disimpan.', ['exception_class' => $e::class]);

            throw new RegistrationFailedException;
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->kk->delete($path);
        } catch (Throwable $e) {
            Log::error('Pendaftaran: file KK yatim gagal dihapus.', ['exception_class' => $e::class, 'path' => $path]);
        }
    }
}
