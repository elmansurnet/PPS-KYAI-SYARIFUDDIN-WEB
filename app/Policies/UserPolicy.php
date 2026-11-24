<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:User');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('View:User');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:User');
    }

    /** Non-superadmin tidak boleh mengubah akun superadmin (mencegah eskalasi hak). */
    public function update(User $user, User $target): bool
    {
        return $user->can('Update:User') && $this->mayManage($user, $target);
    }

    /** Tidak boleh menghapus diri sendiri; akun superadmin hanya oleh superadmin. */
    public function delete(User $user, User $target): bool
    {
        return $user->can('Delete:User') && $user->isNot($target) && $this->mayManage($user, $target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:User');
    }

    private function mayManage(User $actor, User $target): bool
    {
        return $actor->isSuperadmin() || ! $target->isSuperadmin();
    }
}
