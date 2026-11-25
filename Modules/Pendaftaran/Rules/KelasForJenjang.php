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

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $jenjangId = $this->data['jenjang_id'] ?? null;

        $exists = is_string($value) && is_numeric($jenjangId) && KelasOption::query()
            ->where('jenjang_id', (int) $jenjangId)
            ->where('name', $value)
            ->where('is_active', true)
            ->whereHas('jenjang', fn ($q) => $q->where('is_active', true))
            ->exists();

        if (! $exists) {
            $fail('Kelas yang dipilih tidak tersedia untuk jenjang tersebut.');
        }
    }
}
