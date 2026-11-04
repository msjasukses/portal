<?php

namespace App\Models\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use App\Models\Datacenter\Rombel;
use Database\Factories\Kurikulum\TugasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tugas dari guru untuk satu kelas — aplikasi Kurikulum (Ruang Belajar).
 * `kelas_id` = id rombongan belajar, `pegawai_id` = id guru di Data Center.
 */
class Tugas extends Model
{
    /** @use HasFactory<TugasFactory> */
    use HasFactory;

    protected $connection = 'kurikulum';

    protected $table = 'tugas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'kelas_id');
    }

    /**
     * @return BelongsTo<Guru, $this>
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'pegawai_id');
    }

    /** Batas akhir dihitung sampai akhir hari tanggal selesai (sama dengan Kurikulum). */
    public function lewatBatas(): bool
    {
        return $this->tanggal_selesai !== null && now()->gt($this->tanggal_selesai->copy()->endOfDay());
    }
}
