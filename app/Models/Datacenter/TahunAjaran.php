<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahun ajaran — Data Center.
 */
class TahunAjaran extends Model
{
    /** @use HasFactory<TahunAjaranFactory> */
    use HasFactory;

    protected $connection = 'datacenter';

    protected $table = 'tahun_ajaran';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Tahun ajaran berstatus aktif; bila tidak ada, yang terbaru
     * (aturan yang sama dengan aplikasi Absensi).
     */
    public static function aktif(): ?self
    {
        return once(fn () => static::where('is_aktif', true)->first()
            ?? static::orderByDesc('kode_tahun_ajaran')->first());
    }
}
