<?php

namespace Modules\Pendaftaran\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pendaftaran\Models\Applicant;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    public function definition(): array
    {
        return [
            'nik' => (string) fake()->unique()->numerify('################'),
            'nama_lengkap' => fake()->name(),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-18 years', '-10 years')->format('Y-m-d'),
            'jalan_dusun' => fake()->streetName(),
            'rt' => '001',
            'rw' => '002',
            'desa' => fake()->citySuffix().' Makmur',
            'kecamatan' => fake()->city(),
            'kabupaten' => fake()->city(),
            'provinsi' => 'Jawa Timur',
            'pernah_mondok' => false,
            'kelas' => 'Kelas 7',
            'ayah_nama' => fake()->name('male'),
            'ibu_nama' => fake()->name('female'),
            'ayah_hp' => '081234567890',
        ];
    }
}
