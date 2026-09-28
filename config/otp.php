<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP expiry (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a one-time password remains valid. Kept in sync with
    | config('one-time-passwords.default_expires_in_minutes').
    |
    */

    'expires_in_minutes' => (int) env('OTP_EXPIRES_IN_MINUTES', 2),

    /*
    |--------------------------------------------------------------------------
    | Resend cooldown (seconds)
    |--------------------------------------------------------------------------
    |
    | Users cannot request another OTP until this many seconds have passed
    | since the previous code was created.
    |
    */

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 120),

];
