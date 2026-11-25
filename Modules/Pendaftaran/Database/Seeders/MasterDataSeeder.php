<?php

namespace Modules\Pendaftaran\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;

/**
 * Data master awal yang umum untuk pesantren dengan sekolah formal, HANYA untuk instalasi baru.
 * Setelah itu data master dikelola admin dari panel: setiap tabel hanya diisi bila masih kosong,
 * sehingga baris yang dihapus atau diganti namanya tidak muncul lagi saat `db:seed` dijalankan ulang.
 * (Bila admin menghapus SEMUA jenjang atau SEMUA pendidikan, isi awal tabel itu akan dibuat lagi pada
 * seed berikutnya; nonaktifkan baris alih-alih menghapus semuanya.)
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $jenjangs = [
            'SMP/MTs' => ['Kelas 7', 'Kelas 8', 'Kelas 9'],
            'SMA/MA/SMK' => ['Kelas 10', 'Kelas 11', 'Kelas 12'],
        ];

        if (! Jenjang::query()->exists()) {
            $this->seedJenjang($jenjangs);
        }

        if (! PendidikanTerakhir::query()->exists()) {
            foreach (['SD/MI', 'SMP/MTs', 'SMA/MA/SMK', 'Lainnya/Belum sekolah'] as $i => $name) {
                PendidikanTerakhir::create(['name' => $name, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }
    }

    /** @param  array<string, list<string>>  $jenjangs */
    private function seedJenjang(array $jenjangs): void
    {
        $order = 0;
        foreach ($jenjangs as $name => $kelas) {
            $jenjang = Jenjang::create(['name' => $name, 'sort_order' => ++$order, 'is_active' => true]);

            foreach ($kelas as $i => $kelasName) {
                KelasOption::create(['jenjang_id' => $jenjang->id, 'name' => $kelasName, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }
}
}
