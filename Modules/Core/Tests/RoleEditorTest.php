<?php

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Berita\Filament\Resources\PostResource;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Editor role Shield menyimpan dengan syncPermissions berisi HANYA kotak yang tampil di formulir.
 * Semua izin yang dibuat seeder (termasuk izin khusus Export/RevealNik/DownloadKk:Applicant,
 * ManageAll:Post, Manage:SiteSettings) wajib tampil di formulir agar tidak hilang saat role disimpan.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(DatabaseSeeder::class);

    $this->superadmin = User::factory()->create();
    $this->superadmin->assignRole('superadmin');
});

function roleEditorPermissions(string $role): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all();
}

test('every seeded permission is editable in the shield role editor', function () {
    $editable = FilamentShield::getEntitiesPermissions();

    expect(array_values(array_diff(Permission::pluck('name')->all(), $editable)))->toBe([]);
    expect($editable)->toContain('Export:Applicant', 'RevealNik:Applicant', 'DownloadKk:Applicant', 'ManageAll:Post', 'Manage:SiteSettings');
});

test('saving panitia role after unticking one box keeps the custom applicant permissions', function () {
    $this->actingAs($this->superadmin);
    $role = Role::findByName('panitia_pendaftaran', 'web');

    $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);

    $checked = $component->get('data.'.ApplicantResource::class);
    expect($checked)->toContain('Export:Applicant', 'RevealNik:Applicant', 'DownloadKk:Applicant', 'Update:Applicant');

    $component
        ->set('data.'.ApplicantResource::class, array_values(array_diff($checked, ['Update:Applicant'])))
        ->call('save')
        ->assertHasNoErrors();

    expect(roleEditorPermissions('panitia_pendaftaran'))->toBe(collect(['ViewAny:Applicant', 'View:Applicant', 'Export:Applicant', 'RevealNik:Applicant', 'DownloadKk:Applicant'])->sort()->values()->all());
});

test('saving admin role keeps Manage:SiteSettings and ManageAll:Post', function () {
    $this->actingAs($this->superadmin);
    $role = Role::findByName('admin', 'web');
    $before = roleEditorPermissions('admin');

    $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);

    expect($component->get('data.custom_permissions_tab'))->toContain('Manage:SiteSettings');
    $posts = $component->get('data.'.PostResource::class);
    expect($posts)->toContain('ManageAll:Post', 'DeleteAny:Post');

    $component
        ->set('data.'.PostResource::class, array_values(array_diff($posts, ['DeleteAny:Post'])))
        ->call('save')
        ->assertHasNoErrors();

    expect(roleEditorPermissions('admin'))->toBe(array_values(array_diff($before, ['DeleteAny:Post'])));
});

test('superadmin can grant RevealNik:Applicant to a role that lacks it', function () {
    $this->actingAs($this->superadmin);
    $role = Role::findByName('penulis_berita', 'web');
    expect($role->hasPermissionTo('RevealNik:Applicant'))->toBeFalse();

    $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);
    $component
        ->set('data.'.ApplicantResource::class, ['View:Applicant', 'RevealNik:Applicant'])
        ->call('save')
        ->assertHasNoErrors();

    expect(roleEditorPermissions('penulis_berita'))->toContain('RevealNik:Applicant', 'View:Applicant', 'Create:Post');
});

test('admin still cannot open the role editor', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/shield/roles/'.Role::findByName('admin', 'web')->getRouteKey().'/edit')->assertForbidden();
});

test('the superadmin role cannot be renamed or deleted', function () {
    $role = Role::findByName('superadmin', 'web');

    expect(fn () => $role->update(['name' => 'bos']))->toThrow(ValidationException::class);
    expect(fn () => $role->delete())->toThrow(ValidationException::class);

    expect(Role::where('name', 'superadmin')->exists())->toBeTrue();
});

test('renaming the superadmin role from the role editor shows an error and changes nothing', function () {
    $this->actingAs($this->superadmin);
    $role = Role::findByName('superadmin', 'web');

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->set('data.name', 'bos')
        ->call('save')
        ->assertHasErrors(['data.name']);

    expect($role->fresh()->name)->toBe('superadmin');
    expect($this->superadmin->fresh()->isSuperadmin())->toBeTrue();
});

test('other roles can still be renamed and deleted', function () {
    $role = Role::findOrCreate('uji_coba', 'web');
    $role->update(['name' => 'uji_lain']);
    $role->delete();

    expect(Role::where('name', 'like', 'uji_%')->exists())->toBeFalse();
});
