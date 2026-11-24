<?php

namespace Modules\Pendaftaran\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;

/**
 * Data master awal yang umum untuk pesantren dengan sekolah formal. Semuanya dapat diubah admin.
 * firstOrCreate berdasarkan nama: baris yang sudah ada (termasuk hasil edit) tidak ditimpa.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $jenjangs = [
            'SMP/MTs' => ['Kelas 7', 'Kelas 8', 'Kelas 9'],
            'SMA/MA/SMK' => ['Kelas 10', 'Kelas 11', 'Kelas 12'],
        ];

        $order = 0;
        foreach ($jenjangs as $name => $kelas) {
            $jenjang = Jenjang::firstOrCreate(['name' => $name], ['sort_order' => ++$order, 'is_active' => true]);

            foreach ($kelas as $i => $kelasName) {
                KelasOption::firstOrCreate(
                    ['jenjang_id' => $jenjang->id, 'name' => $kelasName],
                    ['sort_order' => $i + 1, 'is_active' => true],
                );
            }
        }

        foreach (['SD/MI', 'SMP/MTs', 'SMA/MA/SMK', 'Lainnya/Belum sekolah'] as $i => $name) {
            PendidikanTerakhir::firstOrCreate(['name' => $name], ['sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
