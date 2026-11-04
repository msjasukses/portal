<?php

namespace App\Models\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use Database\Factories\Kurikulum\AgendaMengajarFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agenda/jurnal mengajar guru per jam pelajaran — aplikasi Kurikulum.
 * `kehadiran_siswa` berisi daftar [siswa_id, nama, status] untuk jam tersebut.
 */
class AgendaMengajar extends Model
{
    /** @use HasFactory<AgendaMengajarFactory> */
    use HasFactory;

    public const STATUS_DITOLAK = 'Ditolak';

    /** Warna penanda kehadiran siswa (urutan & nama status sama dengan Kurikulum). */
    public const WARNA_KEHADIRAN = [
        'Hadir' => 'green',
        'Tidak Hadir' => 'red',
        'Sakit' => 'amber',
        'Izin' => 'sky',
        'Dispensasi' => 'slate',
    ];

    protected $connection = 'kurikulum';

    protected $table = 'agenda_mengajar';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_pengisian' => 'datetime',
            'kehadiran_siswa' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Guru, $this>
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * Agenda kelas tertentu yang tidak ditolak saat divalidasi admin.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUntukKelas(Builder $query, ?int $kelasId): Builder
    {
        return $query->where('kelas_id', $kelasId ?? 0)->where('status', '!=', self::STATUS_DITOLAK);
    }

    /** Status kehadiran seorang siswa yang dicatat guru pada agenda ini. */
    public function kehadiranSiswa(int $siswaId): ?string
    {
        foreach ((array) $this->kehadiran_siswa as $baris) {
            if ((int) ($baris['siswa_id'] ?? 0) === $siswaId) {
                return $baris['status'] ?? null;
            }
        }

        return null;
    }
}
