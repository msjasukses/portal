<?php

namespace App\Models\Kesiswaan;

use Database\Factories\Kesiswaan\JenisPelanggaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kelompok pelanggaran (mis. Kedisiplinan, Kerapihan) — aplikasi Kesiswaan.
 */
class JenisPelanggaran extends Model
{
    /** @use HasFactory<JenisPelanggaranFactory> */
    use HasFactory;

    protected $connection = 'kesiswaan';

    protected $table = 'jenis_pelanggaran';
}
