<?php

namespace Modules\Pendaftaran\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Pendaftaran\Rules\KelasForJenjang;
use Modules\Pendaftaran\Rules\PlainText;

/**
 * Langkah 2: data akademik. Isian pondok sebelumnya hanya dipakai bila pernah_mondok = ya
 * (exclude_unless: nilai tersembunyi untuk jawaban "tidak" dibuang, tidak disimpan).
 */
class AkademikRequest extends WizardStepRequest
{
    public static function step(): int
    {
        return 2;
    }

    public static function normalize(array $input): array
    {
        return self::texts($input, [
            'pernah_mondok', 'pondok_sebelumnya', 'pendidikan_pondok_sebelumnya',
            'pendidikan_terakhir_id', 'jenjang_id', 'kelas',
        ]);
    }

    public static function stepRules(array $data): array
    {
        $previous = ['exclude_unless:pernah_mondok,ya', 'bail', 'required', 'string', 'max:150', new PlainText];

        return [
            'pernah_mondok' => ['required', 'in:ya,tidak'],
            'pondok_sebelumnya' => $previous,
            'pendidikan_pondok_sebelumnya' => $previous,
            'pendidikan_terakhir_id' => ['bail', 'required', 'integer', Rule::exists('pendidikan_terakhirs', 'id')->where('is_active', true)],
            'jenjang_id' => ['bail', 'required', 'integer', Rule::exists('jenjangs', 'id')->where('is_active', true)],
            'kelas' => ['bail', 'required', 'string', 'max:100', new KelasForJenjang],
        ];
    }

    public static function labels(): array
    {
        return [
            'pernah_mondok' => 'Pernah mondok',
            'pondok_sebelumnya' => 'Nama pondok sebelumnya',
            'pendidikan_pondok_sebelumnya' => 'Pendidikan di pondok sebelumnya',
            'pendidikan_terakhir_id' => 'Pendidikan terakhir',
            'jenjang_id' => 'Jenjang',
            'kelas' => 'Kelas',
        ];
    }

    public static function stepMessages(): array
    {
        return [
            'pernah_mondok.required' => 'Pilih apakah calon santri pernah mondok.',
            'pendidikan_terakhir_id.required' => 'Pilih pendidikan terakhir.',
            'jenjang_id.required' => 'Pilih jenjang yang dituju.',
            'kelas.required' => 'Pilih kelas yang dituju.',
        ] + parent::stepMessages();
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return array<string, mixed>
     */
    public static function toAttributes(array $valid): array
    {
        $mondok = $valid['pernah_mondok'] === 'ya';

        return [
            'pernah_mondok' => $mondok,
            'pondok_sebelumnya' => $mondok ? $valid['pondok_sebelumnya'] : null,
            'pendidikan_pondok_sebelumnya' => $mondok ? $valid['pendidikan_pondok_sebelumnya'] : null,
            'pendidikan_terakhir_id' => (int) $valid['pendidikan_terakhir_id'],
            'jenjang_id' => (int) $valid['jenjang_id'],
            'kelas' => $valid['kelas'],
        ];
    }
}
