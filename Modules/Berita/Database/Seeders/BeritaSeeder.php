<?php

namespace Modules\Berita\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Support\DefaultPermissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Berita (konvensi Shield: Aksi:Model). Aman dijalankan ulang: izin dibuat bila belum ada dan
 * setiap izin bawaan diberikan ke role SEKALI saja (DefaultPermissions::grantOnce), sehingga izin yang
 * dicabut superadmin dari panel tidak diberikan lagi. Jalankan setelah RoleSeeder.
 *
 * - admin: semua izin Berita, termasuk ManageAll:Post (melewati pemeriksaan kepemilikan).
 * - penulis_berita: Post (lihat, buat, ubah, hapus; ubah/hapus hanya milik sendiri, lihat PostPolicy)
 *   dan Category (lihat daftar dan buat).
 * - panitia_pendaftaran: tidak ada. superadmin: lewat Gate::before.
 */
class BeritaSeeder extends Seeder
{
    public const WRITER_PERMISSIONS = [
        'ViewAny:Post', 'View:Post', 'Create:Post', 'Update:Post', 'Delete:Post',
        'ViewAny:Category', 'Create:Category',
    ];

    public const ADMIN_ONLY_PERMISSIONS = [
        'DeleteAny:Post', 'ManageAll:Post',
        'View:Category', 'Update:Category', 'Delete:Category', 'DeleteAny:Category',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [...self::WRITER_PERMISSIONS, ...self::ADMIN_ONLY_PERMISSIONS];

        DefaultPermissions::ensure($all);

        DefaultPermissions::grantOnce('admin', $all);
        DefaultPermissions::grantOnce('penulis_berita', self::WRITER_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
