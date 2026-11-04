<?php

namespace App\Models\Kurikulum;

use Illuminate\Database\Eloquent\Model;

/**
 * Akun aplikasi Kurikulum (tabel "users"). Portal Siswa hanya membaca nama
 * guru/admin yang memeriksa jurnal PKL.
 */
class Pengguna extends Model
{
    protected $connection = 'kurikulum';

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'ai_api_key'];
}
