<?php

namespace Modules\Berita\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Berita (konvensi Shield: Aksi:Model). Aman dijalankan ulang: izin dibuat bila belum ada
 * dan role hanya ditambah izin (tidak dikurangi). Jalankan setelah RoleSeeder.
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

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Seeder dari db:seed berjalan tanpa event model, jadi cache izin tidak otomatis dibuang.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web')->givePermissionTo($all);
        Role::findOrCreate('penulis_berita', 'web')->givePermissionTo(self::WRITER_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
