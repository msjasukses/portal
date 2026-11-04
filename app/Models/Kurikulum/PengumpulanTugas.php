<?php

namespace App\Models\Kurikulum;

use Database\Factories\Kurikulum\PengumpulanTugasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban siswa atas satu tugas — aplikasi Kurikulum. Satu baris per siswa
 * per tugas; `siswa_id` = id siswa di Data Center.
 */
class PengumpulanTugas extends Model
{
    /** @use HasFactory<PengumpulanTugasFactory> */
    use HasFactory;

    protected $connection = 'kurikulum';

    protected $table = 'pengumpulan_tugas';

    protected $fillable = [
        'tugas_id',
        'siswa_id',
        'catatan',
        'file',
        'dikumpulkan_pada',
        'terlambat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dikumpulkan_pada' => 'datetime',
            'dinilai_pada' => 'datetime',
            'terlambat' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tugas, $this>
     */
    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class);
    }

    /** Sudah dinilai guru, sehingga siswa tidak boleh mengubah jawabannya lagi. */
    public function sudahDinilai(): bool
    {
        return $this->nilai !== null;
    }
}
