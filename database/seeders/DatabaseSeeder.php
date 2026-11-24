<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Landing\Database\Seeders\LandingSeeder;
use Modules\Landing\Providers\LandingServiceProvider;

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
    }
}
