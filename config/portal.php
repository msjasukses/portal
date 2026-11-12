<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tautan Halaman Utama
    |--------------------------------------------------------------------------
    |
    | Alamat landing page sekolah. Dipakai tombol "Halaman Utama" di sidebar
    | dan tautan kembali di halaman login. Kosong = tautan disembunyikan.
    |
    */

    'landing_url' => env('LANDING_URL'),

    /*
    |--------------------------------------------------------------------------
    | Alamat Aplikasi Ujian (CBT)
    |--------------------------------------------------------------------------
    |
    | Diberitahukan kepada aplikasi ponsel lewat GET /api/v1/arahan, supaya
    | operator cukup mengetik alamat portal saja — tanpa subfolder — dan
    | aplikasi menemukan sendiri di mana ujian berada. Di VPS biasanya portal
    | menempati akar domain dan ujian ada di subfolder, mis. "/cbt".
    |
    | Aturan ujian tetap sepenuhnya milik aplikasi ujian: portal hanya
    | memberitahukan alamatnya, tidak pernah meneruskan soal atau jawaban.
    |
    | Kosong = menu Ujian disembunyikan di aplikasi.
    |
    |     UJIAN_URL=https://sekolah.sch.id/cbt
    |
    */

    'ujian_url' => env('UJIAN_URL'),

];
