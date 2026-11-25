<?php

namespace Modules\Pendaftaran\Exceptions;

use RuntimeException;

/**
 * Pendaftaran gagal disimpan (database atau file). Pengecualian asli sengaja tidak dirantai
 * (pesannya bisa memuat data pribadi); RegisterApplicant sudah mencatat kelasnya saja.
 */
class RegistrationFailedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Pendaftaran gagal disimpan.');
    }
}
