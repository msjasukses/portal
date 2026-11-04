<?php

namespace App\Models\Kesiswaan;

use Database\Factories\Kesiswaan\DataPelanggaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master butir pelanggaran beserta poinnya — aplikasi Kesiswaan.
 */
class DataPelanggaran extends Model
{
    /** @use HasFactory<DataPelanggaranFactory> */
    use HasFactory;

    protected $connection = 'kesiswaan';

    protected $table = 'data_pelanggaran';

    /**
     * @return BelongsTo<JenisPelanggaran, $this>
     */
    public function jenisPelanggaran(): BelongsTo
    {
        return $this->belongsTo(JenisPelanggaran::class);
    }
}
