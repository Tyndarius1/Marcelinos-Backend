<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
