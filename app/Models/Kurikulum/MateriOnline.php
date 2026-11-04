<?php

namespace App\Models\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use App\Models\Datacenter\Rombel;
use Database\Factories\Kurikulum\MateriOnlineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materi online untuk satu kelas — aplikasi Kurikulum (Ruang Belajar).
 */
class MateriOnline extends Model
{
    /** @use HasFactory<MateriOnlineFactory> */
    use HasFactory;

    protected $connection = 'kurikulum';

    protected $table = 'materi_online';

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

    /** Tautan video yang aman dijadikan link (hanya http/https). */
    public function tautanVideo(): ?string
    {
        $tautan = trim((string) $this->link_video);

        return preg_match('#^https?://#i', $tautan) ? $tautan : null;
    }

    /**
     * Penanda isi materi seperti kolom "Isi Materi" di Kurikulum: bentuk
     * lampirannya, peringatan bila tanpa lampiran, dan tanda materi baru.
     *
     * @return list<array{teks: string, warna: string}>
     */
    public function penanda(): array
    {
        $penanda = [];

        if ($this->file) {
            $penanda[] = ['teks' => 'File', 'warna' => 'blue'];
        }

        if ($this->link_video) {
            $penanda[] = ['teks' => 'Video', 'warna' => 'red'];
        }

        if (filled($this->deskripsi)) {
            $penanda[] = ['teks' => 'Deskripsi', 'warna' => 'slate'];
        }

        if (! $this->file && ! $this->link_video) {
            $penanda[] = ['teks' => 'Belum ada lampiran', 'warna' => 'amber'];
        }

        if ($this->created_at && $this->created_at->gt(now()->subDays(7))) {
            $penanda[] = ['teks' => 'Baru', 'warna' => 'green'];
        }

        return $penanda;
    }
}
