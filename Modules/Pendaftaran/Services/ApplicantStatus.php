<?php

namespace Modules\Pendaftaran\Services;

use InvalidArgumentException;
use Modules\Core\Support\AuditLog;
use Modules\Pendaftaran\Models\Applicant;

/**
 * Satu-satunya jalur kode admin untuk mengubah bendera status dan catatan panitia (kolom ini tidak
 * fillable). Pemanggil wajib sudah memeriksa izin update. Audit tanpa data pribadi:
 * - pendaftaran.status  {flag, value, applicant_id}
 * - pendaftaran.catatan {applicant_id} (isi catatan tidak dicatat)
 */
class ApplicantStatus
{
    public const FLAGS = [
        'sudah_dihubungi' => 'Sudah dihubungi',
        'sudah_bayar' => 'Sudah bayar',
        'sudah_masuk' => 'Sudah masuk pesantren',
    ];

    public const NOTE_MAX = 2000;

    public static function set(Applicant $applicant, string $flag, bool $value): void
    {
        if (! array_key_exists($flag, self::FLAGS)) {
            throw new InvalidArgumentException('Bendera status tidak dikenal.');
        }

        if ((bool) $applicant->getAttribute($flag) === $value) {
            return;
        }

        $applicant->forceFill([$flag => $value])->save();

        AuditLog::record('pendaftaran.status', $applicant, [
            'flag' => $flag,
            'value' => $value,
            'applicant_id' => $applicant->getKey(),
        ]);
    }

    public static function setNote(Applicant $applicant, ?string $note): void
    {
        $note = $note === null ? null : trim($note);
        $note = $note === '' ? null : $note;

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            throw new InvalidArgumentException('Catatan terlalu panjang.');
        }

        if ($applicant->catatan_panitia === $note) {
            return;
        }

        $applicant->forceFill(['catatan_panitia' => $note])->save();

        AuditLog::record('pendaftaran.catatan', $applicant, ['applicant_id' => $applicant->getKey()]);
    }
}
