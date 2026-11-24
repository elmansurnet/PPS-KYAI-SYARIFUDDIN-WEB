<?php

use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Core\Filament\Resources\UserResource;
use Modules\Core\Filament\Resources\UserResource\Pages\CreateUser;
use Modules\Core\Filament\Resources\UserResource\Pages\EditUser;
use Modules\Core\Filament\Resources\UserResource\Pages\ListUsers;
use Modules\Core\Tests\Fixtures\Applicant\FakeApplicant;
use Modules\Core\Tests\Fixtures\Applicant\FakeApplicantPolicy;
use Modules\Core\Tests\Fixtures\Applicant\FakeApplicantResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Gate::policy(FakeApplicant::class, FakeApplicantPolicy::class);

    // Izin sumber daya tiruan (konvensi Shield: Aksi:Model) ada sebelum seeder berjalan.
    Permission::findOrCreate('ViewAny:FakeApplicant', 'web');
    $this->seed(RoleSeeder::class);
});

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('test_superadmin_can_manage_role_permissions', function () {
    $this->actingAs(userWithRole('superadmin'));

    expect(RoleResource::canViewAny())->toBeTrue();
    $this->get('/admin/shield/roles')->assertOk();
});

test('test_admin_cannot_manage_role_permissions', function () {
    $this->actingAs(userWithRole('admin'));

    expect(RoleResource::canViewAny())->toBeFalse();
    expect(RoleResource::canCreate())->toBeFalse();
    $this->get('/admin/shield/roles')->assertForbidden();
});

test('test_admin_can_open_all_feature_resources', function () {
    $this->actingAs(userWithRole('admin'));

    expect(UserResource::canViewAny())->toBeTrue();
    expect(FakeApplicantResource::canViewAny())->toBeTrue();
    $this->get('/admin/users')->assertOk();
});

test('test_penulis_berita_cannot_open_pendaftar_resource', function () {
    $this->actingAs(userWithRole('penulis_berita'));

    expect(FakeApplicantResource::canViewAny())->toBeFalse();
    expect(UserResource::canViewAny())->toBeFalse();
    $this->get('/admin/users')->assertForbidden();
});

test('test_guest_is_redirected_to_admin_login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/users')->assertRedirect('/admin/login');
});

test('user without any role cannot enter the panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
});

test('seeder creates exactly the four roles', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'panitia_pendaftaran', 'penulis_berita', 'superadmin']);
});

test('admin holds feature permissions but no role management permissions', function () {
    $admin = Role::findByName('admin');

    expect($admin->hasPermissionTo('ViewAny:FakeApplicant'))->toBeTrue();
    expect($admin->permissions->pluck('name')->filter(fn ($n) => str_ends_with($n, ':Role')))->toBeEmpty();
    expect(Role::findByName('penulis_berita')->permissions)->toBeEmpty();
    expect(Role::findByName('panitia_pendaftaran')->permissions)->toBeEmpty();
});

test('is superadmin helper follows the role', function () {
    expect(userWithRole('superadmin')->isSuperadmin())->toBeTrue();
    expect(userWithRole('admin')->isSuperadmin())->toBeFalse();
});

test('admin can create an account with a hashed password', function () {
    $this->actingAs(userWithRole('admin'));
    $role = Role::findByName('penulis_berita');

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Penulis Baru', 'email' => 'penulis@example.test', 'password' => 'rahasia-12345', 'roles' => [$role->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'penulis@example.test')->firstOrFail();
    expect($created->password)->not->toBe('rahasia-12345');
    expect(Hash::check('rahasia-12345', $created->password))->toBeTrue();
    expect($created->hasRole('penulis_berita'))->toBeTrue();
});

test('admin cannot assign the superadmin role', function () {
    $this->actingAs(userWithRole('admin'));
    $superRole = Role::findByName('superadmin');

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Eskalasi', 'email' => 'esk@example.test', 'password' => 'rahasia-12345', 'roles' => [$superRole->id]])
        ->call('create');

    $created = User::where('email', 'esk@example.test')->first();
    expect($created?->hasRole('superadmin') ?? false)->toBeFalse();
});

test('admin cannot promote an existing account to superadmin', function () {
    $admin = userWithRole('admin');
    $this->actingAs($admin);
    $superRole = Role::findByName('superadmin');

    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->fillForm(['roles' => [$superRole->id]])
        ->call('save');

    expect($admin->fresh()->hasRole('superadmin'))->toBeFalse();
});

test('admin cannot edit or delete a superadmin account', function () {
    $super = userWithRole('superadmin');
    $this->actingAs(userWithRole('admin'));

    expect(UserResource::canEdit($super))->toBeFalse();
    expect(UserResource::canDelete($super))->toBeFalse();
    $this->get("/admin/users/{$super->getKey()}/edit")->assertForbidden();
});

test('superadmin can edit any account', function () {
    $super = userWithRole('superadmin');
    $this->actingAs($super);

    expect(UserResource::canEdit(userWithRole('admin')))->toBeTrue();
    expect(UserResource::canEdit($super))->toBeTrue();
});

test('admin bulk delete cannot remove a superadmin or themselves', function () {
    $super = userWithRole('superadmin');
    $admin = userWithRole('admin');
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction(DeleteBulkAction::class, [$super, $admin]);

    expect(User::find($super->getKey()))->not->toBeNull();
    expect(User::find($admin->getKey()))->not->toBeNull();
});

test('last superadmin cannot be deleted', function () {
    $super = userWithRole('superadmin');

    expect(fn () => $super->delete())->toThrow(LogicException::class);
    expect(User::find($super->getKey()))->not->toBeNull();
});

test('superadmin role cannot be removed from the last superadmin', function () {
    $super = userWithRole('superadmin');

    expect(fn () => $super->removeRole('superadmin'))->toThrow(LogicException::class);
    expect(fn () => $super->syncRoles(['admin']))->toThrow(LogicException::class);
    expect($super->fresh()->isSuperadmin())->toBeTrue();
});

test('superadmin cannot strip their own role through the form when last', function () {
    $super = userWithRole('superadmin');
    $this->actingAs($super);

    Livewire::test(EditUser::class, ['record' => $super->getKey()])
        ->fillForm(['roles' => []])
        ->call('save')
        ->assertHasFormErrors(['roles']);

    expect($super->fresh()->isSuperadmin())->toBeTrue();
});

test('with two superadmins one may be deleted', function () {
    $a = userWithRole('superadmin');
    $b = userWithRole('superadmin');

    expect($a->delete())->toBeTrue();
    expect(User::find($b->getKey()))->not->toBeNull();
});

test('delete action is hidden for the own record', function () {
    $super = userWithRole('superadmin');
    $other = userWithRole('superadmin');
    $this->actingAs($super);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $super)
        ->assertTableActionVisible('delete', $other);
});

test('admin login page renders', function () {
    $this->get('/admin/login')->assertOk();
});
