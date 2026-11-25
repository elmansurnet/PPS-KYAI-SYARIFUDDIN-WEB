<?php

namespace Modules\Pendaftaran\Http\Requests;

use Modules\Pendaftaran\Rules\PlainText;
use Modules\Pendaftaran\Support\FormInput;

/**
 * Langkah 3: data orang tua / wali.
 *
 * - Orang tua masih ada (orang_tua_meninggal = tidak): nama ayah dan ibu, pekerjaan, dan minimal satu
 *   nomor HP wajib. Alamat orang tua wajib kecuali "alamat sama dengan santri" dicentang (disalin
 *   dari langkah 1 saat dikirim). Isian wali dibuang.
 * - Kedua orang tua meninggal (ya): data wali (nama, pekerjaan, HP, alamat) wajib; nama orang tua tetap
 *   wajib, pekerjaan dan HP orang tua boleh kosong; alamat orang tua dibuang.
 */
class OrangTuaRequest extends WizardStepRequest
{
    private const PARENT_TEXT = ['orang_tua_meninggal', 'ayah_pekerjaan', 'ibu_pekerjaan', 'alamat_sama', 'wali_pekerjaan'];

    public static function step(): int
    {
        return 3;
    }

    public static function normalize(array $input): array
    {
        $address = [];
        foreach (SantriRequest::ADDRESS as $field) {
            $address[] = 'ortu_'.$field;
            $address[] = 'wali_'.$field;
        }

        return [
            'ayah_nama' => FormInput::name($input['ayah_nama'] ?? null),
            'ibu_nama' => FormInput::name($input['ibu_nama'] ?? null),
            'wali_nama' => FormInput::name($input['wali_nama'] ?? null),
            'ayah_hp' => FormInput::phone($input['ayah_hp'] ?? null),
            'ibu_hp' => FormInput::phone($input['ibu_hp'] ?? null),
            'wali_hp' => FormInput::phone($input['wali_hp'] ?? null),
        ] + self::texts($input, [...self::PARENT_TEXT, ...$address]);
    }

    public static function stepRules(array $data): array
    {
        $deceased = ($data['orang_tua_meninggal'] ?? null) === 'ya';
        $name = ['bail', 'required', 'string', 'max:100', self::nameRule()];
        $job = [$deceased ? 'nullable' : 'required', 'bail', 'string', 'max:100', new PlainText];
        $wali = ['exclude_unless:orang_tua_meninggal,ya'];

        $rules = [
            'orang_tua_meninggal' => ['required', 'in:ya,tidak'],
            'ayah_nama' => $name,
            'ibu_nama' => $name,
            'ayah_pekerjaan' => $job,
            'ibu_pekerjaan' => $job,
            'ayah_hp' => $deceased ? ['nullable', self::phoneRule()] : ['nullable', 'required_without:ibu_hp', self::phoneRule()],
            'ibu_hp' => ['nullable', self::phoneRule()],
            'alamat_sama' => ['exclude_if:orang_tua_meninggal,ya', 'nullable', 'in:1'],

            'wali_nama' => [...$wali, ...$name],
            'wali_pekerjaan' => [...$wali, 'bail', 'required', 'string', 'max:100', new PlainText],
            'wali_hp' => [...$wali, 'bail', 'required', self::phoneRule()],
        ] + SantriRequest::addressRules('wali_', $wali);

        $copyAddress = $deceased || ($data['alamat_sama'] ?? null) === '1';

        return $rules + SantriRequest::addressRules('ortu_', $copyAddress ? ['exclude'] : []);
    }

    public static function labels(): array
    {
        return [
            'orang_tua_meninggal' => 'Status orang tua',
            'ayah_nama' => 'Nama ayah',
            'ibu_nama' => 'Nama ibu',
            'ayah_pekerjaan' => 'Pekerjaan ayah',
            'ibu_pekerjaan' => 'Pekerjaan ibu',
            'ayah_hp' => 'Nomor HP ayah',
            'ibu_hp' => 'Nomor HP ibu',
            'alamat_sama' => 'Alamat orang tua',
            'wali_nama' => 'Nama wali',
            'wali_pekerjaan' => 'Pekerjaan wali',
            'wali_hp' => 'Nomor HP wali',
        ] + SantriRequest::addressLabels('ortu_', ' orang tua') + SantriRequest::addressLabels('wali_', ' wali');
    }

    public static function stepMessages(): array
    {
        $phone = ':attribute harus nomor HP Indonesia, mis. 0812 3456 7890.';

        return [
            'orang_tua_meninggal.required' => 'Pilih apakah kedua orang tua sudah meninggal.',
            'ayah_nama.regex' => ':attribute hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
            'ibu_nama.regex' => ':attribute hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
            'wali_nama.regex' => ':attribute hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
            'ayah_hp.required_without' => 'Isi nomor HP ayah atau ibu (minimal salah satu).',
            'ayah_hp.regex' => $phone,
            'ibu_hp.regex' => $phone,
            'wali_hp.regex' => $phone,
        ] + SantriRequest::addressMessages('ortu_') + SantriRequest::addressMessages('wali_') + parent::stepMessages();
    }

    /**
     * @param  array<string, mixed>  $valid  data langkah 3 yang sah
     * @param  array<string, mixed>  $santri  data langkah 1 yang sah (sumber salinan alamat)
     * @return array<string, mixed>
     */
    public static function toAttributes(array $valid, array $santri): array
    {
        $deceased = $valid['orang_tua_meninggal'] === 'ya';
        $copy = ! $deceased && ($valid['alamat_sama'] ?? null) === '1';

        $attributes = [
            'orang_tua_meninggal' => $deceased,
            'ayah_nama' => $valid['ayah_nama'],
            'ibu_nama' => $valid['ibu_nama'],
            'ayah_pekerjaan' => $valid['ayah_pekerjaan'] ?? null,
            'ibu_pekerjaan' => $valid['ibu_pekerjaan'] ?? null,
            'ayah_hp' => FormInput::storedPhone($valid['ayah_hp'] ?? null),
            'ibu_hp' => FormInput::storedPhone($valid['ibu_hp'] ?? null),
            'wali_nama' => $deceased ? $valid['wali_nama'] : null,
            'wali_pekerjaan' => $deceased ? $valid['wali_pekerjaan'] : null,
            'wali_hp' => $deceased ? FormInput::storedPhone($valid['wali_hp']) : null,
        ];

        foreach (SantriRequest::ADDRESS as $field) {
            $ortu = match (true) {
                $deceased => null,
                $copy => $santri[$field],
                default => $valid['ortu_'.$field],
            };
            $wali = $deceased ? $valid['wali_'.$field] : null;

            $isNumber = in_array($field, ['rt', 'rw'], true);
            $attributes['ortu_'.$field] = $isNumber ? FormInput::storedRtRw($ortu) : $ortu;
            $attributes['wali_'.$field] = $isNumber ? FormInput::storedRtRw($wali) : $wali;
        }

        return $attributes;
    }
}
