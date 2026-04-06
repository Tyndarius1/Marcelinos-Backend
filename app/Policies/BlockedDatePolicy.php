<?php

namespace App\Policies;

use App\Models\BlockedDate;
use App\Models\User;

class BlockedDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function view(User $user, BlockedDate $blockedDate): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function update(User $user, BlockedDate $blockedDate): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function delete(User $user, BlockedDate $blockedDate): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function restore(User $user, BlockedDate $blockedDate): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }

    public function forceDelete(User $user, BlockedDate $blockedDate): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasPrivilege('manage_blocked_dates');
    }
}
