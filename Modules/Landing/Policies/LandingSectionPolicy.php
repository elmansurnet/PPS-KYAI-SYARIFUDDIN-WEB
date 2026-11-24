<?php

namespace Modules\Landing\Policies;

use App\Models\User;
use Modules\Landing\Models\LandingSection;

/**
 * Kunci bagian tetap, jadi membuat dan menghapus selalu ditolak policy. Superadmin melewati
 * policy lewat Gate::before (Shield), maka pagar tambahan ada di resource: canCreate() false
 * dan tanpa aksi hapus.
 */
class LandingSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:LandingSection');
    }

    public function view(User $user, LandingSection $section): bool
    {
        return $user->can('View:LandingSection');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LandingSection $section): bool
    {
        return $user->can('Update:LandingSection');
    }

    public function delete(User $user, LandingSection $section): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:LandingSection');
    }
}
