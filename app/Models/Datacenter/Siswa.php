<?php

namespace App\Models\Datacenter;

use Database\Factories\Datacenter\SiswaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Siswa pada aplikasi Data Center (tabel "siswa"), sekaligus akun login
 * Portal Siswa: NISN + password yang sama dengan di Data Center.
 *
 * Data siswa hanya dibaca. Satu-satunya yang ditulis adalah penanda login
 * (jumlah gagal, kunci sementara, terakhir aktif) — aturannya sama dengan
 * endpoint verifikasi siswa di Data Center.
 */
class Siswa extends Authenticatable
{
    /** @use HasFactory<SiswaFactory> */
    use HasFactory;

    /** Batas salah password sebelum akun dikunci sementara (sama dengan Data Center). */
    public const MAKS_GAGAL_LOGIN = 5;

    public const MENIT_KUNCI = 15;

    protected $connection = 'datacenter';

    protected $table = 'siswa';

    protected $hidden = ['password', 'remember_token'];

    /**
     * Portal tidak memakai "ingat saya", jadi token remember milik Data Center
     * tidak pernah disentuh (termasuk saat logout).
     */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_aktif' => 'boolean',
            'locked_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SiswaRombel, $this>
     */
    public function siswaRombel(): HasMany
    {
        return $this->hasMany(SiswaRombel::class, 'siswa_id');
    }

    public function akunAktif(): bool
    {
        return $this->is_aktif && strtolower((string) ($this->account_status ?? 'active')) === 'active';
    }

    public function sedangTerkunci(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Nomor induk yang dipakai aplikasi Absensi sebagai kunci catatan absensi
     * dan jadwal magang: NIS, atau NISN bila NIS kosong.
     */
    public function kunciAbsensi(): string
    {
        return filled($this->nis) ? (string) $this->nis : (string) $this->nisn;
    }

    /**
     * NIS dan NISN — jadwal magang di Kurikulum dicocokkan ke salah satunya.
     *
     * @return list<string>
     */
    public function nomorInduk(): array
    {
        return array_values(array_filter([(string) $this->nis, (string) $this->nisn], 'strlen'));
    }

    /** Penempatan kelas siswa pada tahun ajaran aktif. */
    public function penempatanAktif(): ?SiswaRombel
    {
        return once(function () {
            $tahunAjaran = TahunAjaran::aktif();

            return $tahunAjaran
                ? $this->siswaRombel()->with('rombel')->where('tahun_ajaran_id', $tahunAjaran->id)->first()
                : null;
        });
    }

    public function rombelAktif(): ?Rombel
    {
        return $this->penempatanAktif()?->rombel;
    }

    /**
     * Semua kelas yang pernah ditempati siswa (lintas tahun ajaran).
     *
     * @return list<int>
     */
    public function semuaRombelId(): array
    {
        return once(fn () => $this->siswaRombel()->pluck('rombongan_belajar_id')->map(fn ($id) => (int) $id)->all());
    }
}
