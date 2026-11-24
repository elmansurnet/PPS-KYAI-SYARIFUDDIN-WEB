<?php

namespace Modules\Pendaftaran\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin data master Pendaftaran (konvensi Shield: Aksi:Model). Aman dijalankan ulang. Jalankan setelah RoleSeeder.
 *
 * - admin: semua izin data master. superadmin: lewat Gate::before.
 * - penulis_berita dan panitia_pendaftaran: tidak ada (izin pendaftar untuk panitia ditambah di Task 12).
 * Data master awal ada di MasterDataSeeder (dipisah agar izin bisa diterapkan tanpa menyentuh data).
 */
class PendaftaranSeeder extends Seeder
{
    public const MASTER_MODELS = ['Jenjang', 'KelasOption', 'PendidikanTerakhir'];

    public const MASTER_ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Reorder'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach (self::MASTER_MODELS as $model) {
            foreach (self::MASTER_ACTIONS as $action) {
                $all[] = "{$action}:{$model}";
            }
        }

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web')->givePermissionTo($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
