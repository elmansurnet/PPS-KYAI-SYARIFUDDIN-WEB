<?php

namespace Modules\Pendaftaran\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Modules\Pendaftaran\Support\KkFileInspector;

/** File KK: tidak kosong dan isinya benar-benar JPEG, PNG, atau PDF (lihat KkFileInspector). */
class KkFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Unggahan file KK gagal. Silakan pilih ulang file.');

            return;
        }

        $bytes = (string) @file_get_contents($value->getRealPath());

        if ($bytes === '') {
            $fail('File KK kosong. Silakan pilih ulang foto atau PDF KK.');

            return;
        }

        if (KkFileInspector::detect($bytes) === null) {
            $fail('File KK harus berupa foto JPG/PNG atau PDF.');
        }
    }
}
