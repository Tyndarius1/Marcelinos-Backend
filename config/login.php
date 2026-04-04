<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Failed login attempt limits (Filament /web login)
    |--------------------------------------------------------------------------
    |
    | After this many wrong passwords for the same email from the same IP,
    | further attempts are blocked until the decay window passes. This is in
    | addition to Filament's built-in limit of 5 login submits per minute per IP.
    |
    */

    'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),

    'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 900),

];
