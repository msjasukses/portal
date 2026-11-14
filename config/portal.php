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

    /*
    |--------------------------------------------------------------------------
    | Manifes Rilis APK
    |--------------------------------------------------------------------------
    |
    | Berkas JSON yang mencatat versi APK terbaru. Isinya disisipkan ke
    | GET /api/v1/arahan, dan dari situlah aplikasi ponsel tahu ada pembaruan.
    |
    | Letaknya sengaja bersebelahan dengan berkas APK-nya sendiri di
    | public/rilis. Sebuah rilis adalah peristiwa mengirim berkas, jadi
    | catatannya sebaiknya bepergian bersama berkas itu: mengunggah APK lalu
    | lupa menyunting satu baris di .env di tempat lain adalah kesalahan yang
    | menunggu terjadi, dan kegagalannya diam.
    |
    | Berkas yang tidak ada berarti sekolah ini belum pernah mengunggah rilis.
    | Itu keadaan yang sah, bukan galat.
    |
    */

    'rilis_manifes' => env('PORTAL_RILIS_MANIFES', public_path('rilis/terkini.json')),

];
