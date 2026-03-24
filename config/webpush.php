<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Configuration
    |--------------------------------------------------------------------------
    |
    | VAPID (Voluntary Application Server Identification) keys are required
    | for sending push notifications. Generate them using:
    | php artisan webpush:vapid
    |
    | Or use an online generator:
    | - https://web-push-codelab.glitch.me/
    | - https://vapidkeys.com/
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:contact@eyamo.cm'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];

