<?php

namespace App\Policies;

use App\Models\BedSpecification;
use App\Models\User;

class BedSpecificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function view(User $user, BedSpecification $bedSpecification): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function update(User $user, BedSpecification $bedSpecification): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function delete(User $user, BedSpecification $bedSpecification): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function restore(User $user, BedSpecification $bedSpecification): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function forceDelete(User $user, BedSpecification $bedSpecification): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
