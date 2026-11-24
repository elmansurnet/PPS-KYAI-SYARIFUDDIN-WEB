<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Berita\Database\Seeders\BeritaSeeder;
use Modules\Berita\Providers\BeritaServiceProvider;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Landing\Database\Seeders\LandingSeeder;
use Modules\Landing\Providers\LandingServiceProvider;
use Modules\Pendaftaran\Database\Seeders\MasterDataSeeder;
use Modules\Pendaftaran\Database\Seeders\PendaftaranSeeder;
use Modules\Pendaftaran\Providers\PendaftaranServiceProvider;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Seeder modul hanya dijalankan bila modulnya aktif (tabelnya baru ada bila modul dimuat).
        if (in_array(LandingServiceProvider::class, config('modules.enabled', []), true)) {
            $this->call(LandingSeeder::class);
        }

        if (in_array(BeritaServiceProvider::class, config('modules.enabled', []), true)) {
            $this->call(BeritaSeeder::class);
        }

        if (in_array(PendaftaranServiceProvider::class, config('modules.enabled', []), true)) {
            $this->call(PendaftaranSeeder::class);
            $this->call(MasterDataSeeder::class);
        }
    }
}
