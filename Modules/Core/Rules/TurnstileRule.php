<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Services\TurnstileVerifier;

class TurnstileRule implements ValidationRule
{
    /** Tetap dievaluasi saat field kosong/tidak dikirim. */
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $token = is_string($value) ? $value : '';

        if (! app(TurnstileVerifier::class)->passes($token, request()->ip())) {
            $fail('Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.');
        }
    }
}
