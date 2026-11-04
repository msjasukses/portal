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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Aplikasi Absensi (presensi) — endpoint barcode_api.php yang dipakai menu
    | Absen Barcode di Portal Siswa. 'barcode_key' harus sama dengan
    | BARCODE_API_KEY pada config.local.php aplikasi Absensi; kosong = menu
    | Absen Barcode dimatikan.
    */
    'absensi' => [
        'url' => env('ABSENSI_URL'),
        'barcode_key' => env('ABSENSI_BARCODE_KEY'),
        'timeout' => (int) env('ABSENSI_TIMEOUT', 8),
    ],

];
