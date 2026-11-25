<?php

namespace Modules\Pendaftaran\Exceptions;

use RuntimeException;

/** NIK sudah terdaftar. Sengaja tanpa data apa pun tentang pendaftar yang sudah ada. */
class DuplicateNikException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('NIK sudah terdaftar.');
    }
}
