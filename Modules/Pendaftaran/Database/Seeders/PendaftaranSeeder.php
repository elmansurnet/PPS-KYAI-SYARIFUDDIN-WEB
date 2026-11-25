<?php

namespace Modules\Pendaftaran\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Support\DefaultPermissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin modul Pendaftaran (konvensi Shield: Aksi:Model). Aman dijalankan ulang. Jalankan setelah RoleSeeder.
 *
 * - admin: semua izin data master dan pendaftar. superadmin: lewat Gate::before.
 * - panitia_pendaftaran: pendaftar saja (lihat, ubah status, ekspor, lihat NIK, unduh KK); TIDAK bisa menghapus.
 * - penulis_berita: tidak ada.
 * Setiap izin bawaan diberikan ke role SEKALI saja (DefaultPermissions::grantOnce, dicatat di tabel
 * seeded_role_permissions): izin yang dicabut superadmin dari panel tidak diberikan lagi saat seeder
 * dijalankan ulang; hanya izin yang belum pernah diberikan (izin baru) yang ditambahkan. Tidak ada izin
 * yang dicabut oleh seeder.
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

        DefaultPermissions::ensure([...$master, ...$applicant]);

        DefaultPermissions::grantOnce('admin', [...$master, ...$applicant]);
        DefaultPermissions::grantOnce('panitia_pendaftaran', $panitia);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
