<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Database\Seeders\RoleSeeder;

uses(RefreshDatabase::class);

test('seeder creates the superadmin from env variables', function () {
    config(['modules.superadmin' => ['email' => 'owner@example.test', 'password' => 'kata-sandi-uji-1']]);

    $this->seed(RoleSeeder::class);

    $user = User::where('email', 'owner@example.test')->firstOrFail();
    expect($user->isSuperadmin())->toBeTrue();
    expect(Hash::check('kata-sandi-uji-1', $user->password))->toBeTrue();
});

test('seeder is idempotent for the superadmin account', function () {
    config(['modules.superadmin' => ['email' => 'owner@example.test', 'password' => 'kata-sandi-uji-1']]);

    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(User::where('email', 'owner@example.test')->count())->toBe(1);
});

test('seeder skips the superadmin when env variables are missing', function () {
    config(['modules.superadmin' => ['email' => null, 'password' => null]]);

    $this->seed(RoleSeeder::class);

    expect(User::count())->toBe(0);
});
