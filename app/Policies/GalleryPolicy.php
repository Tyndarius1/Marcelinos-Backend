<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function view(User $user, Gallery $gallery): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function restore(User $user, Gallery $gallery): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function forceDelete(User $user, Gallery $gallery): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }
}
