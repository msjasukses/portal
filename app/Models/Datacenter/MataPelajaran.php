<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mata pelajaran — Data Center.
 */
class MataPelajaran extends Model
{
    /** @use HasFactory<MataPelajaranFactory> */
    use HasFactory;

    protected $connection = 'datacenter';

    protected $table = 'mata_pelajaran';
}
