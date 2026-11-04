<?php

namespace App\Models\Absensi;

use App\Models\Datacenter\Siswa;
use Carbon\CarbonInterface;
use Database\Factories\Absensi\JadwalMagangFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jadwal magang/PKL siswa dari aplikasi Absensi (tabel jadwal_magang),
 * hanya dibaca. Kolom `nis` merujuk NIS siswa (atau NISN bila NIS kosong);
 * `hari` berisi nomor hari ISO dipisah koma (1 = Senin .. 7 = Minggu).
 */
class JadwalMagang extends Model
{
    /** @use HasFactory<JadwalMagangFactory> */
    use HasFactory;

    public const NAMA_HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $connection = 'absensi';

    protected $table = 'jadwal_magang';

    public $timestamps = false;

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
     * Jadwal milik seorang siswa (dicocokkan lewat NIS atau NISN, sama dengan Kurikulum).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMilik(Builder $query, Siswa $siswa): Builder
    {
        return $query->whereIn('nis', $siswa->nomorInduk());
    }

    /**
     * @return list<int> nomor hari ISO yang dijadwalkan magang
     */
    public function daftarHari(): array
    {
        return array_values(array_map('intval', array_filter(explode(',', (string) $this->hari))));
    }

    public function namaHari(): string
    {
        return implode(', ', array_map(fn ($hari) => self::NAMA_HARI[$hari] ?? $hari, $this->daftarHari()));
    }

    /** Tanggal tersebut masuk periode dan jatuh pada hari magang. */
    public function berlakuPada(CarbonInterface $tanggal): bool
    {
        return $tanggal->betweenIncluded($this->tanggal_mulai, $this->tanggal_selesai)
            && in_array($tanggal->dayOfWeekIso, $this->daftarHari(), true);
    }

    /** Periode magang sedang berlangsung hari ini. */
    public function sedangBerjalan(): bool
    {
        return today()->betweenIncluded($this->tanggal_mulai, $this->tanggal_selesai);
    }

    /**
     * Semua tanggal magang dalam jadwal ini sampai hari ini, terbaru lebih dulu.
     *
     * @return list<Carbon>
     */
    public function tanggalMagang(): array
    {
        $akhir = Carbon::parse(min($this->tanggal_selesai, today()));
        $tanggal = [];

        for ($hari = $akhir->copy(); $hari->gte($this->tanggal_mulai); $hari->subDay()) {
            if (in_array($hari->dayOfWeekIso, $this->daftarHari(), true)) {
                $tanggal[] = $hari->copy();
            }
        }

        return $tanggal;
    }
}
