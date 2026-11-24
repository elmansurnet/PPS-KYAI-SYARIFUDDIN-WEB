<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Kunci uji Cloudflare (selalu lolos) HANYA bila APP_ENV persis 'testing'. Di local wajib isi
    // kunci di .env (Cloudflare menyediakan kunci uji untuk ditempel); kosong berarti gagal tertutup.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY') ?: (env('APP_ENV') === 'testing' ? '1x00000000000000000000AA' : null),
        'secret' => env('TURNSTILE_SECRET_KEY') ?: (env('APP_ENV') === 'testing' ? '1x0000000000000000000000000000000AA' : null),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
