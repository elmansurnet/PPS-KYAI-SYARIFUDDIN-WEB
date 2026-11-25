<?php

namespace Modules\Pendaftaran\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin modul Pendaftaran (konvensi Shield: Aksi:Model). Aman dijalankan ulang. Jalankan setelah RoleSeeder.
 *
 * - admin: semua izin data master dan pendaftar. superadmin: lewat Gate::before.
 * - panitia_pendaftaran: pendaftar saja (lihat, ubah status, ekspor, lihat NIK, unduh KK); TIDAK bisa menghapus.
 * - penulis_berita: tidak ada.
 * Izin hanya ditambahkan (givePermissionTo), tidak dicabut: perubahan superadmin dari panel tidak ditimpa,
 * kecuali izin baru yang belum pernah ada.
 * Data master awal ada di MasterDataSeeder (dipisah agar izin bisa diterapkan tanpa menyentuh data).
 */
class PendaftaranSeeder extends Seeder
{
    public const MASTER_MODELS = ['Jenjang', 'KelasOption', 'PendidikanTerakhir'];

    public const MASTER_ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Reorder'];

    /** Izin pendaftar. Export, RevealNik, dan DownloadKk adalah izin khusus (bukan bawaan Shield). */
    public const APPLICANT_ACTIONS = ['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny', 'Export', 'RevealNik', 'DownloadKk'];

    public const PANITIA_APPLICANT_ACTIONS = ['ViewAny', 'View', 'Update', 'Export', 'RevealNik', 'DownloadKk'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $master = [];
        foreach (self::MASTER_MODELS as $model) {
            foreach (self::MASTER_ACTIONS as $action) {
                $master[] = "{$action}:{$model}";
            }
        }

        $applicant = array_map(fn (string $action) => "{$action}:Applicant", self::APPLICANT_ACTIONS);
        $panitia = array_map(fn (string $action) => "{$action}:Applicant", self::PANITIA_APPLICANT_ACTIONS);

        foreach ([...$master, ...$applicant] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web')->givePermissionTo([...$master, ...$applicant]);
        Role::findOrCreate('panitia_pendaftaran', 'web')->givePermissionTo($panitia);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
