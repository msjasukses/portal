<?php

namespace App\Models\Kurikulum;

use App\Models\Absensi\JadwalMagang;
use Database\Factories\Kurikulum\JurnalPklFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jurnal harian PKL/magang siswa — aplikasi Kurikulum. Tanggal yang boleh
 * diisi mengikuti jadwal magang di aplikasi Absensi.
 */
class JurnalPkl extends Model
{
    /** @use HasFactory<JurnalPklFactory> */
    use HasFactory;

    public const STATUS_MENUNGGU = 'Menunggu';

    public const STATUS_DISETUJUI = 'Disetujui';

    public const STATUS_REVISI = 'Revisi';

    public const WARNA_STATUS = [
        self::STATUS_MENUNGGU => 'amber',
        self::STATUS_DISETUJUI => 'green',
        self::STATUS_REVISI => 'red',
    ];

    protected $connection = 'kurikulum';

    protected $table = 'jurnal_pkl';

    protected $fillable = [
        'siswa_id',
        'jadwal_magang_id',
        'tempat',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'kegiatan',
        'hasil',
        'kendala',
        'foto',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'diperiksa_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JadwalMagang, $this>
     */
    public function jadwalMagang(): BelongsTo
    {
        return $this->belongsTo(JadwalMagang::class);
    }

    /**
     * Akun Kurikulum (guru/admin) yang memeriksa jurnal.
     *
     * @return BelongsTo<Pengguna, $this>
     */
    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperiksa_oleh');
    }

    /** Siswa hanya boleh mengubah jurnal yang belum disetujui. */
    public function bisaDiubahSiswa(): bool
    {
        return $this->status !== self::STATUS_DISETUJUI;
    }

    public function warnaStatus(): string
    {
        return self::WARNA_STATUS[$this->status] ?? 'slate';
    }
}
