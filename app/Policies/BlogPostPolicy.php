<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function restore(User $user, BlogPost $blogPost): bool
    {
        return $user->hasPrivilege('manage_galleries');
    }

    public function forceDelete(User $user, BlogPost $blogPost): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
