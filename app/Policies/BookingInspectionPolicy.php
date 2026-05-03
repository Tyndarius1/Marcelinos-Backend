<?php

namespace App\Policies;

use App\Models\BookingInspection;
use App\Models\User;

class BookingInspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }

    public function view(User $user, BookingInspection $bookingInspection): bool
    {
        return $user->hasPrivilege('manage_bookings');
    }
}
