<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\BukuDigital;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu buku katalog digital.
 *
 * Dua alamat bacanya sengaja dipisah, bukan disatukan jadi satu "url". Buku
 * yang berkasnya sudah diunduh sekolah dibaca lewat portal — butuh token, dan
 * tetap terbuka saat SIBI mati atau internet sekolah padam. Buku yang belum
 * diunduh hanya ada di SIBI, jadi alamatnya alamat luar; menyalurkannya lewat
 * portal berarti puluhan MB lewat PHP, dan token portal ikut terbawa ke luar.
 *
 * @mixin BukuDigital
 */
class BukuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lokal = $this->sudahDiunduh();

        return [
            'id' => $this->id,
            'judul' => (string) $this->judul,
            'penulis' => $this->penulis,
            'penerbit' => $this->penerbit,
            'tahun_terbit' => $this->tahun_terbit,
            'isbn' => $this->isbn,
            'bahasa' => $this->bahasa,
            'jenjang' => $this->jenjang,
            'tingkat_kelas' => $this->tingkat_kelas,
            'mata_pelajaran' => $this->mata_pelajaran,
            'kategori' => $this->kategori,
            'jenis_buku' => $this->jenis_buku,
            'kurikulum' => $this->kurikulum,
            'tipe' => (string) $this->tipe,
            'edisi' => $this->edisi,
            'deskripsi' => $this->deskripsi,
            'ukuran' => $this->ukuranFileManusiawi(),
            'tersedia_offline' => $lokal,
            'penanda' => $this->penanda(),

            // Cover: berkas sekolah bila ada, selain itu gambar di SIBI.
            'cover_url' => $this->coverLokal() ? route('api.berkas.buku-cover', $this->id) : null,
            'cover_luar' => $this->tautanLuar($this->url_cover),

            // Isi buku. Hanya PDF yang bisa dibaca lewat portal — aturan yang
            // sama dengan aksi "baca" di aplikasi Kurikulum.
            'baca_url' => $lokal && $this->tipe === 'pdf' ? route('api.berkas.buku', $this->id) : null,
            'url_sibi' => $this->tautanLuar($this->url_file),

            // Daftar isi hanya menyertai halaman rincian, bukan daftar katalog.
            'bab' => $this->whenLoaded('bab', fn () => $this->bab->map(fn ($bab) => [
                'nomor' => $bab->nomor_bab,
                'judul' => (string) $bab->judul_bab,
                'halaman' => $bab->halaman,
                'sub' => $bab->subBab->map(fn ($sub) => [
                    'nomor' => $sub->nomor_bab,
                    'judul' => (string) $sub->judul_bab,
                    'halaman' => $sub->halaman,
                ])->all(),
            ])->all()),
        ];
    }
}
