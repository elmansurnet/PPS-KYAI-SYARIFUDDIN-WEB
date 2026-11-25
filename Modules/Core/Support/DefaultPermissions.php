<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pemberian izin bawaan oleh seeder, SEKALI per pasangan (role, izin).
 *
 * Setiap pasangan yang sudah diberikan dicatat di tabel `seeded_role_permissions`. Menjalankan seeder
 * lagi (mis. `db:seed --force` saat pembaruan) hanya memberi pasangan yang belum tercatat, yaitu izin
 * baru dari fitur/modul baru. Izin yang dicabut superadmin dari panel tetap tercabut. Seeder TIDAK
 * PERNAH memakai syncPermissions dan tidak pernah mencabut izin.
 *
 * Bila role belum ada (instalasi baru, atau role dihapus superadmin lalu dibuat ulang seeder),
 * catatannya dikosongkan sehingga role itu menerima semua izin bawaannya lagi.
 */
final class DefaultPermissions
{
    public const TABLE = 'seeded_role_permissions';

    /** Cari atau buat role; role yang baru dibuat kehilangan catatan lamanya. */
    public static function role(string $name, string $guard = 'web'): Role
    {
        $role = Role::findOrCreate($name, $guard);

        if ($role->wasRecentlyCreated) {
            DB::table(self::TABLE)->where('role_name', $name)->where('guard_name', $guard)->delete();
        }

        return $role;
    }

    /**
     * Pastikan izin ada (dibuat bila belum).
     *
     * @param  iterable<string>  $names
     */
    public static function ensure(iterable $names, string $guard = 'web'): void
    {
        foreach ($names as $name) {
            Permission::findOrCreate($name, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Beri role izin bawaan yang belum pernah diberikan seeder (lihat docblock kelas).
     *
     * @param  iterable<string>  $permissions
     */
    public static function grantOnce(string $roleName, iterable $permissions, string $guard = 'web'): void
    {
        $names = collect($permissions)->map(fn ($name) => (string) $name)->unique()->values();

        if ($names->isEmpty()) {
            return;
        }

        self::ensure($names, $guard);
        $role = self::role($roleName, $guard);

        $recorded = DB::table(self::TABLE)
            ->where('role_name', $roleName)
            ->where('guard_name', $guard)
            ->pluck('permission_name');

        $pending = $names->diff($recorded)->values();

        if ($pending->isEmpty()) {
            return;
        }

        $role->givePermissionTo($pending->all());

        $now = now();
        DB::table(self::TABLE)->insertOrIgnore($pending->map(fn (string $name) => [
            'role_name' => $roleName,
            'permission_name' => $name,
            'guard_name' => $guard,
            'created_at' => $now,
        ])->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
