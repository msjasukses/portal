<?php

use App\Models\Datacenter\Siswa;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Portal Siswa hanya punya satu guard: akun siswa aplikasi Data Center.
    |
    */

    'defaults' => [
        'guard' => 'siswa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        // Akun siswa dari aplikasi Data Center (login NISN).
        'siswa' => [
            'driver' => 'session',
            'provider' => 'siswa',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'siswa' => [
            'driver' => 'eloquent',
            'model' => Siswa::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => 10800,

];
