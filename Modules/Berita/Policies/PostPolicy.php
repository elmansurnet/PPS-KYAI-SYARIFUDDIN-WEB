<?php

namespace Modules\Berita\Policies;

use App\Models\User;
use Modules\Berita\Models\Post;

/**
 * Kepemilikan: izin Update:Post dan Delete:Post saja tidak cukup; pemilik berita (author_id)
 * atau pemegang izin ManageAll:Post (admin) yang boleh mengubah/menghapus. Penulis berita
 * hanya bisa mengubah dan menghapus tulisannya sendiri. Superadmin melewati policy lewat
 * Gate::before (Shield). Hapus massal (deleteAny) hanya untuk pemegang DeleteAny:Post (admin).
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Post');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('View:Post');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Post');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('Update:Post') && $this->owns($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('Delete:Post') && $this->owns($user, $post);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Post');
    }

    private function owns(User $user, Post $post): bool
    {
        return $user->can('ManageAll:Post') || ($post->author_id !== null && $post->author_id === $user->getKey());
    }
}
