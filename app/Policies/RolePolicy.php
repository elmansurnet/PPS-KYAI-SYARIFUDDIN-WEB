<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

/**
 * Pengelolaan role/izin. Superadmin lolos lewat Gate::before (Shield); role
 * lain hanya bisa bila diberi izin eksplisit, dan seeder tidak memberikannya.
 */
class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Role');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('View:Role');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Role');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('Update:Role');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('Delete:Role');
    }
}
