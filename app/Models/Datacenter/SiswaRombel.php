<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\SiswaRombelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penempatan siswa di kelas (rombongan belajar) per tahun ajaran — Data Center.
 */
class SiswaRombel extends Model
{
    /** @use HasFactory<SiswaRombelFactory> */
    use HasFactory;

    protected $connection = 'datacenter';

    protected $table = 'siswa_rombel';

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombongan_belajar_id');
    }
}
