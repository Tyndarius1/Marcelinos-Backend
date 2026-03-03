<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization
|--------------------------------------------------------------------------
|
| Here you may register event broadcasting channel authorization callbacks.
| These callbacks determine whether the current user can listen on the
| channel. Use Channel::name() for public channels (no auth).
|
| Convention: use a consistent prefix per domain, e.g. "bookings", "admin".
|
*/

// Public channel: no authorization (anyone can subscribe)
// Broadcast::channel('bookings', fn () => true);

// Private channel: only authenticated users matching the booking can listen
Broadcast::channel(
    'booking.{reference}',
    function ($user, string $reference) {
        // Staff/admin can listen to any booking; guests only their own.
        if (in_array($user->role ?? null, ['admin', 'staff'], true)) {
            return true;
        }
        // If you have a relation like user->bookings, check reference here
        return true;
    }
);

// Admin/Staff dashboard channel (private)
Broadcast::channel(
    'admin.dashboard',
    function ($user) {
        return in_array($user->role ?? null, ['admin', 'staff'], true);
    }
);

Broadcast::channel('booking.{reference}.cancelled', function ($user, string $reference) { 
    return in_array($user->role ?? null, ['admin', 'staff'], true) 
        || true; // allow guest if needed 
});