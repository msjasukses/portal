<?php

namespace App\Models\Kurikulum;

use Database\Factories\Kurikulum\BukuDigitalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Satu buku pada katalog buku digital aplikasi Kurikulum — hasil sinkron dari
 * katalog SIBI (Sistem Informasi Perbukuan Indonesia) milik Kemendikdasmen.
 *
 * Berkas PDF dan covernya bisa sudah diunduh ke penyimpanan Kurikulum, bisa
 * juga masih tinggal di server SIBI. Perbedaan itu menentukan lewat mana
 * aplikasi membukanya, jadi kelas ini yang menjawabnya — bukan aplikasi.
 */
class BukuDigital extends Model
{
    /** @use HasFactory<BukuDigitalFactory> */
    use HasFactory;

    protected $connection = 'kurikulum';

    protected $table = 'buku_digital';

    protected $casts = [
        'diunduh_pada' => 'datetime',
        'disinkron_pada' => 'datetime',
        'ukuran_file' => 'integer',
    ];

    /** Bab utama beserta sub babnya, urut seperti di buku. */
    public function bab(): HasMany
    {
        return $this->hasMany(BukuDigitalBab::class, 'buku_digital_id')
            ->where('level', 1)
            ->orderBy('urutan');
    }

    /** Berkas bukunya sudah ada di penyimpanan aplikasi Kurikulum. */
    public function sudahDiunduh(): bool
    {
        return filled($this->file_path) && Storage::disk('kurikulum')->exists($this->file_path);
    }

    /** Covernya sudah ada di penyimpanan aplikasi Kurikulum. */
    public function coverLokal(): bool
    {
        return filled($this->cover_path) && Storage::disk('kurikulum')->exists($this->cover_path);
    }

    /** Ukuran berkas dalam satuan yang enak dibaca, mis. "12,4 MB". */
    public function ukuranFileManusiawi(): ?string
    {
        if (! $this->ukuran_file) {
            return null;
        }

        $mb = $this->ukuran_file / 1048576;

        return $mb < 1
            ? number_format($this->ukuran_file / 1024, 0, ',', '.').' KB'
            : number_format($mb, 1, ',', '.').' MB';
    }

    /** Tautan hanya dipakai bila benar-benar tautan web. */
    public function tautanLuar(?string $alamat): ?string
    {
        $alamat = trim((string) $alamat);

        return preg_match('#^https?://#i', $alamat) ? $alamat : null;
    }

    /**
     * Penanda isi buku seperti badge di katalog Kurikulum: bentuk berkasnya,
     * dan apakah berkasnya sudah ada di server sekolah.
     *
     * @return list<array{teks: string, warna: string}>
     */
    public function penanda(): array
    {
        $penanda = [match ($this->tipe) {
            'audio' => ['teks' => 'Audio', 'warna' => 'blue'],
            'interactive' => ['teks' => 'Interaktif', 'warna' => 'amber'],
            default => ['teks' => 'PDF', 'warna' => 'red'],
        }];

        if ($this->sudahDiunduh()) {
            $penanda[] = ['teks' => 'Tersedia di sekolah', 'warna' => 'green'];
        } elseif ($this->tautanLuar($this->url_file)) {
            $penanda[] = ['teks' => 'Dibuka dari SIBI', 'warna' => 'slate'];
        } else {
            $penanda[] = ['teks' => 'Belum ada berkas', 'warna' => 'amber'];
        }

        if (filled($this->tingkat_kelas)) {
            $penanda[] = ['teks' => 'Kelas '.$this->tingkat_kelas, 'warna' => 'slate'];
        }

        return $penanda;
    }

    /** Pencarian bebas pada judul, penulis, penerbit, dan ISBN. */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($kata) {
            foreach (['judul', 'penulis', 'penerbit', 'isbn'] as $kolom) {
                $q->orWhere($kolom, 'like', '%'.$kata.'%');
            }
        });
    }
}
