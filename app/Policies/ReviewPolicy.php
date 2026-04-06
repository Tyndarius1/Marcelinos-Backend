<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function view(User $user, Review $review): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function update(User $user, Review $review): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function restore(User $user, Review $review): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }

    public function forceDelete(User $user, Review $review): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPrivilege('manage_reviews');
    }
}
