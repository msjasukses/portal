<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\RombelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kelas / rombongan belajar (tabel "rombongan_belajar") — Data Center.
 */
class Rombel extends Model
{
    /** @use HasFactory<RombelFactory> */
    use HasFactory;

    protected $connection = 'datacenter';

    protected $table = 'rombongan_belajar';

    /**
     * @return BelongsTo<Guru, $this>
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_id');
    }
}
