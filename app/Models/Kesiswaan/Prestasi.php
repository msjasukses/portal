<?php

namespace App\Models\Kesiswaan;

use Database\Factories\Kesiswaan\PrestasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Prestasi siswa — aplikasi Kesiswaan. `siswa_id` = id siswa di Data Center.
 */
class Prestasi extends Model
{
    /** @use HasFactory<PrestasiFactory> */
    use HasFactory;

    protected $connection = 'kesiswaan';

    protected $table = 'prestasi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
