<?php

namespace Modules\Pendaftaran\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Support\NikHasher;
use RuntimeException;

/**
 * Hitung ulang nik_hash semua pendaftar dari NIK terdekripsi. Dipakai setelah NIK_HMAC_KEY diganti.
 * Idempotent; berjalan per potongan. Tidak pernah mencetak NIK.
 */
class RehashNikCommand extends Command
{
    protected $signature = 'pendaftaran:rehash-nik';

    protected $description = 'Hitung ulang nik_hash semua pendaftar (jalankan setelah NIK_HMAC_KEY diganti)';

    public function handle(): int
    {
        try {
            NikHasher::hash('0'); // gagal lebih awal bila kunci kosong
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $updated = 0;
        $unchanged = 0;
        $failed = [];

        Applicant::query()->chunkById(200, function ($applicants) use (&$updated, &$unchanged, &$failed) {
            foreach ($applicants as $applicant) {
                try {
                    $hash = NikHasher::hash((string) $applicant->nik);
                } catch (DecryptException) {
                    $failed[] = $applicant->id;

                    continue;
                }

                if ($applicant->nik_hash === $hash) {
                    $unchanged++;

                    continue;
                }

                $applicant->forceFill(['nik_hash' => $hash])->saveQuietly();
                $updated++;
            }
        });

        $this->info("Selesai: {$updated} diperbarui, {$unchanged} sudah sesuai.");

        if ($failed !== []) {
            $this->error('Gagal didekripsi (periksa APP_KEY), id: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
