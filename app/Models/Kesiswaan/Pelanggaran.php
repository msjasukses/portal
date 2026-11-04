<?php

namespace App\Models\Kesiswaan;

use Database\Factories\Kesiswaan\PelanggaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan pelanggaran siswa — aplikasi Kesiswaan. `siswa_id` = id siswa di Data Center.
 */
class Pelanggaran extends Model
{
    /** @use HasFactory<PelanggaranFactory> */
    use HasFactory;

    protected $connection = 'kesiswaan';

    protected $table = 'pelanggaran';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'poin' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DataPelanggaran, $this>
     */
    public function dataPelanggaran(): BelongsTo
    {
        return $this->belongsTo(DataPelanggaran::class);
    }
}
