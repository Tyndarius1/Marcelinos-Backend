<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'api' => [
        'key' => env('API_KEY'),
    ],

    /*
     * Booking cancel/reschedule OTP is sent by email (see BookingActionOtpService).
     */
    'booking_action_otp' => [
        'max_sends_before_cooldown' => (int) env('BOOKING_ACTION_OTP_MAX_SENDS', 3),
        'cooldown_seconds' => (int) env('BOOKING_ACTION_OTP_COOLDOWN_SECONDS', 60),
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'otp_url' => env('SEMAPHORE_OTP_URL', 'https://api.semaphore.co/api/v4/otp'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
    ],

];
