<?php

namespace App\Policies;

use App\Models\RoomBlockedDate;
use App\Models\User;

class RoomBlockedDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function view(User $user, RoomBlockedDate $roomBlockedDate): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function update(User $user, RoomBlockedDate $roomBlockedDate): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function delete(User $user, RoomBlockedDate $roomBlockedDate): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function restore(User $user, RoomBlockedDate $roomBlockedDate): bool
    {
        return $user->hasPrivilege('manage_rooms');
    }

    public function forceDelete(User $user, RoomBlockedDate $roomBlockedDate): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
