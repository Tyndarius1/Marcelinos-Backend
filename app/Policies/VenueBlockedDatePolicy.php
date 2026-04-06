<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VenueBlockedDate;

class VenueBlockedDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function view(User $user, VenueBlockedDate $venueBlockedDate): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function update(User $user, VenueBlockedDate $venueBlockedDate): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function delete(User $user, VenueBlockedDate $venueBlockedDate): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function restore(User $user, VenueBlockedDate $venueBlockedDate): bool
    {
        return $user->hasPrivilege('manage_venues');
    }

    public function forceDelete(User $user, VenueBlockedDate $venueBlockedDate): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
