<?php

namespace Modules\Pendaftaran\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teks polos: tanpa "<" atau ">" (tidak ada HTML), tanpa karakter kontrol (\p{Cc}), dan tanpa karakter pengarah
 * teks bidi (U+200E/F, U+061C, U+202A-202E, U+2066-2069), UTF-8 sah. Karakter tak terlihat non-bidi (zero-width,
 * BOM, soft hyphen) dibuang FormInput sebelum validasi.
 * Spasi/tab/baris baru sudah dirapatkan menjadi satu spasi sebelum validasi (WizardStepRequest).
 */
class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[<>\p{Cc}\x{200E}\x{200F}\x{061C}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) !== 0) {
            $fail(':attribute tidak boleh memuat tanda < atau > maupun karakter kontrol atau pengarah teks.');
        }
    }
}
