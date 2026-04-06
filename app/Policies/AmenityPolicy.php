<?php

namespace App\Policies;

use App\Models\Amenity;
use App\Models\User;

class AmenityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function view(User $user, Amenity $amenity): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function update(User $user, Amenity $amenity): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function delete(User $user, Amenity $amenity): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function restore(User $user, Amenity $amenity): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }

    public function forceDelete(User $user, Amenity $amenity): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPrivilege('manage_amenities');
    }
}
