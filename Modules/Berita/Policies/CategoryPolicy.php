<?php

namespace Modules\Berita\Policies;

use App\Models\User;
use Modules\Berita\Models\Category;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Category');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('View:Category');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Category');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('Update:Category');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('Delete:Category');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Category');
    }
}
