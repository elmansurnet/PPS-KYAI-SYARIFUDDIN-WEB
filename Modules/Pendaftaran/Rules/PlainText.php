<?php

namespace Modules\Pendaftaran\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teks polos: tanpa "<" atau ">" (tidak ada HTML) dan tanpa karakter kontrol, UTF-8 sah.
 * Spasi/tab/baris baru sudah dirapatkan menjadi satu spasi sebelum validasi (WizardStepRequest).
 */
class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[<>\p{Cc}]/u', $value) !== 0) {
            $fail(':attribute tidak boleh memuat tanda < atau > maupun karakter kontrol.');
        }
    }
}
