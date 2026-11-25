<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Core\Support\DefaultPermissions;
use Modules\Pendaftaran\Database\Seeders\PendaftaranSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Seeder dijalankan ulang di setiap pembaruan (`db:seed --force`). Izin bawaan diberikan sekali per
 * pasangan (role, izin); izin yang dicabut superadmin tidak boleh kembali.
 */

uses(RefreshDatabase::class);

function seededRole(string $name): Role
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return Role::findByName($name, 'web');
}

test('fresh install seeds every default permission', function () {
    $this->seed(DatabaseSeeder::class);

    expect(seededRole('admin')->hasPermissionTo('RevealNik:Applicant'))->toBeTrue()
        ->and(seededRole('admin')->hasPermissionTo('Manage:SiteSettings'))->toBeTrue()
        ->and(seededRole('admin')->hasPermissionTo('ManageAll:Post'))->toBeTrue()
        ->and(seededRole('admin')->hasPermissionTo('Update:LandingSection'))->toBeTrue()
        ->and(seededRole('admin')->hasPermissionTo('ViewAny:Role'))->toBeFalse()
        ->and(seededRole('panitia_pendaftaran')->hasPermissionTo('DownloadKk:Applicant'))->toBeTrue()
        ->and(seededRole('panitia_pendaftaran')->hasPermissionTo('Delete:Applicant'))->toBeFalse()
        ->and(seededRole('penulis_berita')->hasPermissionTo('Create:Post'))->toBeTrue();
});

test('fresh install also works when shield generated the permissions before seeding', function () {
    // Urutan instalasi di DEPLOY: shield:generate --option=permissions lalu db:seed.
    foreach (['RevealNik:Applicant', 'Create:Post', 'Manage:SiteSettings', 'ViewAny:Jenjang'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->seed(DatabaseSeeder::class);

    expect(seededRole('admin')->hasPermissionTo('RevealNik:Applicant'))->toBeTrue()
        ->and(seededRole('admin')->hasPermissionTo('ViewAny:Jenjang'))->toBeTrue()
        ->and(seededRole('penulis_berita')->hasPermissionTo('Create:Post'))->toBeTrue()
        ->and(seededRole('panitia_pendaftaran')->hasPermissionTo('RevealNik:Applicant'))->toBeTrue();
});

test('a permission revoked by the superadmin stays revoked after re-seeding', function () {
    $this->seed(DatabaseSeeder::class);

    seededRole('admin')->revokePermissionTo(['RevealNik:Applicant', 'Manage:SiteSettings', 'ManageAll:Post', 'Update:LandingSection']);
    seededRole('panitia_pendaftaran')->revokePermissionTo('Export:Applicant');
    seededRole('penulis_berita')->revokePermissionTo('Delete:Post');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(seededRole('admin')->hasPermissionTo('RevealNik:Applicant'))->toBeFalse()
        ->and(seededRole('admin')->hasPermissionTo('Manage:SiteSettings'))->toBeFalse()
        ->and(seededRole('admin')->hasPermissionTo('ManageAll:Post'))->toBeFalse()
        ->and(seededRole('admin')->hasPermissionTo('Update:LandingSection'))->toBeFalse()
        ->and(seededRole('admin')->hasPermissionTo('Export:Applicant'))->toBeTrue()
        ->and(seededRole('panitia_pendaftaran')->hasPermissionTo('Export:Applicant'))->toBeFalse()
        ->and(seededRole('penulis_berita')->hasPermissionTo('Delete:Post'))->toBeFalse();
});

test('permissions granted by the superadmin are never removed by seeding', function () {
    $this->seed(DatabaseSeeder::class);
    seededRole('penulis_berita')->givePermissionTo('RevealNik:Applicant');

    $this->seed(DatabaseSeeder::class);

    expect(seededRole('penulis_berita')->hasPermissionTo('RevealNik:Applicant'))->toBeTrue();
});

test('a permission added later is granted once, then respects revocation', function () {
    $this->seed(DatabaseSeeder::class);

    // Fitur baru: izin baru muncul (mis. dari shield:generate) dan seeder dijalankan ulang.
    Permission::findOrCreate('ViewAny:Galeri', 'web');
    $this->seed(RoleSeeder::class);
    expect(seededRole('admin')->hasPermissionTo('ViewAny:Galeri'))->toBeTrue();

    seededRole('admin')->revokePermissionTo('ViewAny:Galeri');
    $this->seed(RoleSeeder::class);
    expect(seededRole('admin')->hasPermissionTo('ViewAny:Galeri'))->toBeFalse();

    // Izin baru di seeder modul juga diberikan sekali.
    DefaultPermissions::grantOnce('panitia_pendaftaran', ['Print:Applicant']);
    expect(seededRole('panitia_pendaftaran')->hasPermissionTo('Print:Applicant'))->toBeTrue();
});

test('a core role deleted and recreated by the seeder gets its defaults again', function () {
    $this->seed(DatabaseSeeder::class);
    seededRole('panitia_pendaftaran')->delete();

    $this->seed(DatabaseSeeder::class);

    expect(seededRole('panitia_pendaftaran')->hasPermissionTo('View:Applicant'))->toBeTrue();
});

test('module permission seeder alone is idempotent', function () {
    $this->seed(PendaftaranSeeder::class);
    $count = DB::table(DefaultPermissions::TABLE)->count();

    $this->seed(PendaftaranSeeder::class);

    expect(DB::table(DefaultPermissions::TABLE)->count())->toBe($count);
});
