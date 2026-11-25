<?php

namespace Modules\Core\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Modules\Core\Support\DefaultPermissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dasar dan izin awalnya. Aman dijalankan ulang dan TIDAK menimpa perubahan superadmin:
 * izin bawaan diberikan sekali per pasangan (role, izin) lewat DefaultPermissions::grantOnce, jadi
 * izin yang dicabut dari panel tetap tercabut. Izin yang belum pernah diberikan (mis. izin baru hasil
 * `php artisan shield:generate --all --panel=admin --option=permissions` setelah modul baru) diberikan
 * sekali ke admin saat seeder ini dijalankan lagi. JANGAN jalankan shield:generate tanpa
 * --option=permissions karena menimpa policy buatan tangan.
 */
class RoleSeeder extends Seeder
{
    public const ROLES = ['superadmin', 'admin', 'penulis_berita', 'panitia_pendaftaran'];

    /** Izin sumber daya Core (konvensi Shield: Aksi:Model). */
    private const CORE_PERMISSIONS = [
        'ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User', 'DeleteAny:User',
        'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role',
        // Izin halaman (bukan sumber daya): pengaturan situs.
        'Manage:SiteSettings',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DefaultPermissions::ensure(self::CORE_PERMISSIONS);

        foreach (self::ROLES as $name) {
            DefaultPermissions::role($name);
        }

        // superadmin: semua lewat Gate::before (Shield), tanpa izin eksplisit.
        // admin: semua izin fitur kecuali pengelolaan role/izin.
        // penulis_berita dan panitia_pendaftaran: kosong; modul fiturnya yang memberi izin.
        $featurePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'not like', '%:Role')
            ->get();
        DefaultPermissions::grantOnce('admin', $featurePermissions->pluck('name'));

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
