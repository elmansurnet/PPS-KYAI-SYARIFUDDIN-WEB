<?php

namespace Modules\Pendaftaran\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Pendaftaran\Models\KelasOption;

/**
 * Kelas harus label KelasOption aktif milik jenjang aktif yang dipilih (jenjang_id di data yang sama).
 * Kelas dari jenjang lain atau kelas buatan sendiri ditolak.
 */
class KelasForJenjang implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Nama kelas kanonis (persis seperti di master data) untuk kelas aktif milik jenjang aktif, atau null.
     * Pencocokan tidak peka huruf besar/kecil dan tidak bergantung collation database.
     */
    public static function canonical(int $jenjangId, string $value): ?string
    {
        $wanted = mb_strtolower(trim($value));

        return KelasOption::query()
            ->where('jenjang_id', $jenjangId)
            ->where('is_active', true)
            ->whereHas('jenjang', fn ($q) => $q->where('is_active', true))
            ->pluck('name')
            ->first(fn (string $name) => mb_strtolower($name) === $wanted);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $jenjangId = $this->data['jenjang_id'] ?? null;

        if (! is_string($value) || ! is_numeric($jenjangId) || self::canonical((int) $jenjangId, $value) === null) {
            $fail('Kelas yang dipilih tidak tersedia untuk jenjang tersebut.');
        }
    }
}
