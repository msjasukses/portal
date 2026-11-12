<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token masuk aplikasi ponsel siswa.
 *
 * Portal di peramban memakai sesi; aplikasi ponsel tidak punya sesi, jadi tiap
 * login melahirkan satu token acak yang dikirim kembali pada setiap permintaan
 * sebagai "Authorization: Bearer ...".
 *
 * Tabel ini tinggal di database portal sendiri (portal_siswa), bukan di
 * database aplikasi sekolah: portal hanya boleh membaca database tetangganya.
 *
 * Yang disimpan hanya sidik SHA-256-nya. Baris tabel ini terbaca siapa pun yang
 * memegang cadangan database, dan token mentah di sana sama nilainya dengan
 * password siswa sampai masa berlakunya habis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa_api_token', function (Blueprint $table) {
            $table->id();

            // Tanpa foreign key: tabel siswa tinggal di database "datacenter".
            $table->unsignedBigInteger('siswa_id')->index();

            $table->string('token', 64)->unique();
            $table->string('perangkat', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('terakhir_dipakai_at')->nullable();
            $table->timestamp('kedaluwarsa_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_api_token');
    }
};
