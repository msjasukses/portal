<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\GuruFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Guru / PTK — Data Center. Portal Siswa hanya memakai namanya.
 */
class Guru extends Model
{
    /** @use HasFactory<GuruFactory> */
    use HasFactory;

    protected $connection = 'datacenter';

    protected $table = 'guru';

    protected $hidden = ['password', 'remember_token'];
}
