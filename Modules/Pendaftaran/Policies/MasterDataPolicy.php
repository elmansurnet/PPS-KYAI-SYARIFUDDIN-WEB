<?php

namespace Modules\Pendaftaran\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Kebijakan data master: izin bergaya Shield "Aksi:Model" (ditulis tangan, bukan hasil generate).
 */
abstract class MasterDataPolicy
{
    abstract protected function subject(): string;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:'.$this->subject());
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('View:'.$this->subject());
    }

    public function create(User $user): bool
    {
        return $user->can('Create:'.$this->subject());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('Update:'.$this->subject());
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('Delete:'.$this->subject());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:'.$this->subject());
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:'.$this->subject());
    }
}
