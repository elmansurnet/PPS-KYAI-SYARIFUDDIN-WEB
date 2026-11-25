<?php

namespace Modules\Pendaftaran\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Modules\Pendaftaran\Support\FormInput;
use Modules\Pendaftaran\Support\RegistrationDraft;
use Modules\Pendaftaran\Support\WizardSteps;

/**
 * Dasar validasi satu langkah wizard (langkah 1-3).
 *
 * Aturan, normalisasi, label, dan pesan bersifat statis supaya kiriman akhir bisa memvalidasi ulang
 * seluruh draf dengan aturan yang persis sama (validatorFor()).
 *
 * Saat gagal, isian TIDAK di-flash polos ke sesi (perilaku bawaan Laravel withInput()); isian disimpan
 * ke draf terenkripsi (old[n]) dan yang di-flash hanya pesan galat (pesan tidak pernah memuat nilai isian).
 */
abstract class WizardStepRequest extends FormRequest
{
    abstract public static function step(): int;

    /**
     * Normalisasi isian mentah: hanya kunci yang dikenal langkah ini.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    abstract public static function normalize(array $input): array;

    /**
     * @param  array<string, mixed>  $data  data yang sudah dinormalisasi (untuk aturan bersyarat)
     * @return array<string, mixed>
     */
    abstract public static function stepRules(array $data): array;

    /** @return array<string, string> */
    abstract public static function labels(): array;

    /** @return array<string, mixed> */
    public static function stepMessages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ['string' => ':attribute maksimal :max karakter.'],
            'regex' => ':attribute tidak sesuai format.',
            'in' => 'Pilihan :attribute tidak valid.',
            'integer' => 'Pilihan :attribute tidak valid.',
            'exists' => ':attribute yang dipilih tidak tersedia.',
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function validatorFor(array $data): ValidatorContract
    {
        $data = static::normalize($data);

        return Validator::make($data, static::stepRules($data), static::stepMessages(), static::labels());
    }

    /**
     * Isian mentah yang aman disimpan sebagai old[n]: hanya kunci yang dikenal, string pendek.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function oldInput(array $input): array
    {
        $old = [];

        foreach (static::normalize($input) as $key => $value) {
            if (is_string($value) || is_int($value)) {
                $old[$key] = mb_substr((string) $value, 0, 300);
            }
        }

        return $old;
    }

    /** Pola nama orang: huruf (termasuk beraksen), spasi, apostrof, titik, koma, tanda hubung. */
    protected static function nameRule(): string
    {
        return "regex:/^[\\pL\\pM' .,\\-]+$/u";
    }

    protected static function phoneRule(): string
    {
        return 'regex:/^(\+?62|0)8[0-9]{8,12}$/';
    }

    /** @param  array<string, mixed>  $input */
    protected static function texts(array $input, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = FormInput::text($input[$key] ?? null);
        }

        return $out;
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(static::normalize($this->all()));
    }

    public function rules(): array
    {
        return static::stepRules($this->all());
    }

    public function messages(): array
    {
        return static::stepMessages();
    }

    public function attributes(): array
    {
        return static::labels();
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        RegistrationDraft::for($this)->putOld(static::step(), static::oldInput($this->all()));

        throw new HttpResponseException(
            redirect()->to(WizardSteps::url(static::step()), 303)->withErrors($validator)
        );
    }
}
