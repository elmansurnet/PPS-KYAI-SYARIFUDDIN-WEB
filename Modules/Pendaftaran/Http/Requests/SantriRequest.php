<?php

namespace Modules\Pendaftaran\Http\Requests;

use Modules\Pendaftaran\Rules\PlainText;
use Modules\Pendaftaran\Support\FormInput;

/** Langkah 1: data calon santri. */
class SantriRequest extends WizardStepRequest
{
    public const ADDRESS = ['jalan_dusun', 'rt', 'rw', 'desa', 'kecamatan', 'kabupaten', 'provinsi'];

    public static function step(): int
    {
        return 1;
    }

    public static function normalize(array $input): array
    {
        return [
            'nik' => FormInput::nik($input['nik'] ?? null),
            'nama_lengkap' => FormInput::name($input['nama_lengkap'] ?? null),
            'tempat_lahir' => FormInput::text($input['tempat_lahir'] ?? null),
            'tanggal_lahir' => FormInput::date($input['tanggal_lahir'] ?? null),
        ] + self::texts($input, self::ADDRESS);
    }

    public static function stepRules(array $data): array
    {
        return [
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'nama_lengkap' => ['bail', 'required', 'string', 'max:100', self::nameRule()],
            'tempat_lahir' => ['bail', 'required', 'string', 'max:100', new PlainText],
            'tanggal_lahir' => [
                'bail', 'required', 'date_format:Y-m-d',
                'before_or_equal:'.now()->subYears(self::minAge())->toDateString(),
                'after_or_equal:'.now()->subYears(self::maxAge())->toDateString(),
            ],
        ] + self::addressRules('');
    }

    /**
     * Aturan alamat (santri, orang tua, atau wali) dengan awalan kolom.
     *
     * @return array<string, list<mixed>>
     */
    public static function addressRules(string $prefix, array $extra = []): array
    {
        $text = fn (int $max) => [...$extra, 'bail', 'required', 'string', "max:{$max}", new PlainText];
        $number = [...$extra, 'bail', 'required', 'regex:/^\d{1,3}$/'];

        return [
            $prefix.'jalan_dusun' => $text(150),
            $prefix.'rt' => $number,
            $prefix.'rw' => $number,
            $prefix.'desa' => $text(100),
            $prefix.'kecamatan' => $text(100),
            $prefix.'kabupaten' => $text(100),
            $prefix.'provinsi' => $text(100),
        ];
    }

    /** @return array<string, string> */
    public static function addressLabels(string $prefix, string $suffix = ''): array
    {
        return [
            $prefix.'jalan_dusun' => 'Jalan/dusun'.$suffix,
            $prefix.'rt' => 'RT'.$suffix,
            $prefix.'rw' => 'RW'.$suffix,
            $prefix.'desa' => 'Desa/kelurahan'.$suffix,
            $prefix.'kecamatan' => 'Kecamatan'.$suffix,
            $prefix.'kabupaten' => 'Kabupaten/kota'.$suffix,
            $prefix.'provinsi' => 'Provinsi'.$suffix,
        ];
    }

    /** @return array<string, string> */
    public static function addressMessages(string $prefix): array
    {
        return [
            $prefix.'rt.regex' => ':attribute harus berupa angka 1 sampai 3 digit.',
            $prefix.'rw.regex' => ':attribute harus berupa angka 1 sampai 3 digit.',
        ];
    }

    public static function labels(): array
    {
        return [
            'nik' => 'NIK',
            'nama_lengkap' => 'Nama lengkap',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
        ] + self::addressLabels('');
    }

    public static function stepMessages(): array
    {
        $age = 'Tanggal lahir tidak sesuai untuk calon santri (usia '.self::minAge().' sampai '.self::maxAge().' tahun).';

        return [
            'nik.required' => 'NIK harus tepat 16 digit angka sesuai Kartu Keluarga.',
            'nik.string' => 'NIK harus tepat 16 digit angka sesuai Kartu Keluarga.',
            'nik.regex' => 'NIK harus tepat 16 digit angka sesuai Kartu Keluarga.',
            'nama_lengkap.regex' => 'Nama lengkap hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
            'tanggal_lahir.date_format' => 'Tanggal lahir tidak valid. Gunakan format tanggal-bulan-tahun, mis. 17-08-2014.',
            'tanggal_lahir.before_or_equal' => $age,
            'tanggal_lahir.after_or_equal' => $age,
        ] + self::addressMessages('') + parent::stepMessages();
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return array<string, mixed>
     */
    public static function toAttributes(array $valid): array
    {
        $attributes = [
            'nik' => $valid['nik'],
            'nama_lengkap' => $valid['nama_lengkap'],
            'tempat_lahir' => $valid['tempat_lahir'],
            'tanggal_lahir' => $valid['tanggal_lahir'],
        ];

        foreach (self::ADDRESS as $field) {
            $attributes[$field] = in_array($field, ['rt', 'rw'], true) ? FormInput::storedRtRw($valid[$field]) : $valid[$field];
        }

        return $attributes;
    }

    public static function minAge(): int
    {
        return (int) config('pendaftaran.usia_min', 5);
    }

    public static function maxAge(): int
    {
        return (int) config('pendaftaran.usia_maks', 30);
    }
}
