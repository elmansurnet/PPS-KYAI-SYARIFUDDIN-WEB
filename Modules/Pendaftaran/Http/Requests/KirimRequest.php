<?php

namespace Modules\Pendaftaran\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Modules\Core\Rules\TurnstileRule;
use Modules\Pendaftaran\Rules\KkFile;
use Modules\Pendaftaran\Support\KkFileInspector;
use Modules\Pendaftaran\Support\WizardSteps;

/**
 * Langkah 4: file KK dan verifikasi Turnstile. Data langkah 1-3 divalidasi ulang terpisah oleh controller.
 * Gagal tertutup: tanpa site key Turnstile pendaftaran diblokir (pesan umum ke pengguna, detail ke log).
 */
class KirimRequest extends FormRequest
{
    public const TURNSTILE_FIELD = 'cf-turnstile-response';

    public const TURNSTILE_UNAVAILABLE = 'Verifikasi keamanan tidak tersedia, coba lagi nanti.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kk' => [
                'bail', 'required', 'file',
                'max:'.(int) config('pendaftaran.kk_max_kb', 2048),
                'extensions:'.implode(',', KkFileInspector::EXTENSIONS),
                new KkFile,
                'mimetypes:'.implode(',', KkFileInspector::MIMES),
            ],
            self::TURNSTILE_FIELD => [self::turnstileAvailable() ? new TurnstileRule : self::unavailableRule()],
        ];
    }

    public function messages(): array
    {
        $kb = (int) config('pendaftaran.kk_max_kb', 2048);
        $limit = $kb % 1024 === 0 ? ($kb / 1024).' MB' : $kb.' KB';

        return [
            'kk.required' => 'Pilih file foto atau PDF Kartu Keluarga.',
            'kk.file' => 'Unggahan file KK gagal. Pastikan ukuran file maksimal '.$limit.' lalu pilih ulang.',
            'kk.max' => 'Ukuran file KK maksimal '.$limit.'.',
            'kk.extensions' => 'File KK harus berupa foto JPG/PNG atau PDF.',
            'kk.mimetypes' => 'File KK harus berupa foto JPG/PNG atau PDF.',
        ];
    }

    public static function turnstileAvailable(): bool
    {
        if (filled(config('services.turnstile.site_key'))) {
            return true;
        }

        Log::error('Turnstile tidak dikonfigurasi: services.turnstile.site_key (env TURNSTILE_SITE_KEY) kosong; formulir pendaftaran diblokir.');

        return false;
    }

    private static function unavailableRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public bool $implicit = true;

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $fail(KirimRequest::TURNSTILE_UNAVAILABLE);
            }
        };
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        // Tanpa withInput(): tidak ada isian yang di-flash (file tidak bisa diisi ulang).
        throw new HttpResponseException(redirect()->to(WizardSteps::url(4), 303)->withErrors($validator));
    }
}
