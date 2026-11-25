<?php

namespace Modules\Pendaftaran\Support;

/** Judul dan URL keempat langkah wizard pendaftaran. */
class WizardSteps
{
    public const TOTAL = 4;

    /** @var array<int, array{title: string, short: string}> */
    private const STEPS = [
        1 => ['title' => 'Data calon santri', 'short' => 'Calon santri'],
        2 => ['title' => 'Data akademik', 'short' => 'Akademik'],
        3 => ['title' => 'Data orang tua / wali', 'short' => 'Orang tua'],
        4 => ['title' => 'Kartu keluarga dan kirim', 'short' => 'Kirim'],
    ];

    /** @return array<int, array{title: string, short: string}> */
    public static function all(): array
    {
        return self::STEPS;
    }

    public static function title(int $step): string
    {
        return self::STEPS[$step]['title'];
    }

    public static function url(int $step): string
    {
        return $step === 1 ? route('daftar') : route('daftar.langkah', $step);
    }
}
