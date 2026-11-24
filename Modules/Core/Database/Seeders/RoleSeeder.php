<?php

namespace Modules\Core\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dasar dan izin awalnya. Aman dijalankan ulang: setelah modul baru
 * menambah izin, jalankan `php artisan shield:generate --all --panel=admin --option=permissions`
 * (hanya izin; JANGAN tanpa --option karena menimpa UserPolicy buatan tangan), lalu jalankan
 * seeder ini lagi supaya role admin ikut menerima izin fitur yang baru.
 */
class RoleSeeder extends Seeder
{
    public const ROLES = ['superadmin', 'admin', 'penulis_berita', 'panitia_pendaftaran'];

    /** Izin sumber daya Core (konvensi Shield: Aksi:Model). */
    private const CORE_PERMISSIONS = [
        'ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User', 'DeleteAny:User',
        'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::CORE_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $name) {
            Role::findOrCreate($name, 'web');
        }

        // superadmin: semua lewat Gate::before (Shield), tanpa izin eksplisit.
        // admin: semua izin fitur kecuali pengelolaan role/izin.
        // penulis_berita dan panitia_pendaftaran: kosong; modul fiturnya yang memberi izin.
        $featurePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'not like', '%:Role')
            ->get();
        Role::findByName('admin', 'web')->syncPermissions($featurePermissions);

        $this->seedSuperadmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedSuperadmin(): void
    {
        $email = config('modules.superadmin.email');
        $password = config('modules.superadmin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD belum diisi di .env: akun superadmin tidak dibuat.');

            return;
        }

        // Akun yang sudah ada tidak diubah kata sandinya; hanya dipastikan berperan superadmin.
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Superadmin', 'password' => $password],
        );

        $user->assignRole('superadmin');
    }
}
