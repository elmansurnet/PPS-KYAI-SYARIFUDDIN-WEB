<?php

namespace Modules\Pendaftaran\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Modules\Pendaftaran\Models\Applicant;

/**
 * Tampilan NIK di admin/ekspor yang tidak pernah melempar: NIK yang tidak bisa didekripsi
 * (APP_KEY salah/berganti, payload rusak) tampil sebagai penanda, bukan galat halaman.
 */
class NikDisplay
{
    public const UNREADABLE = '(tidak terbaca)';

    public static function masked(Applicant $applicant): string
    {
        try {
            return $applicant->nikMasked();
        } catch (DecryptException) {
            return self::UNREADABLE;
        }
    }

    public static function full(Applicant $applicant): string
    {
        try {
            return (string) $applicant->nik;
        } catch (DecryptException) {
            return self::UNREADABLE;
        }
    }

    /** NIK lengkap bila $reveal, selain itu tersamar. */
    public static function for(Applicant $applicant, bool $reveal): string
    {
        return $reveal ? self::full($applicant) : self::masked($applicant);
    }
}
