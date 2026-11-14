<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\ModulAjar;
use App\Support\TeksKaya;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modul ajar yang dipilih guru pada sebuah agenda mengajar.
 *
 * Isi tiap bagian datang dari editor teks kaya aplikasi Kurikulum, jadi
 * penyaringannya dijalankan ulang di sini — sama seperti saat ditampilkan di
 * peramban, dan dengan alasan yang sama: yang menulisnya guru, tetapi yang
 * menanggung akibat HTML-nya adalah perangkat siswa.
 *
 * Bagian yang kosong tidak ikut dikirim. Modul yang disusun lewat Generator
 * kerap hanya mengisi separuh bagiannya, dan aplikasi tidak perlu tahu nama
 * bagian yang tidak ada isinya — ia cukup memajang apa yang diberikan,
 * berurutan.
 *
 * @mixin ModulAjar
 */
class ModulAjarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => (string) $this->judul,
            'mapel' => $this->mataPelajaran?->nama_mapel,
            'fase' => $this->fase,
            'semester' => $this->semester,
            'pertemuan_ke' => $this->pertemuan_ke,
            'jumlah_jam' => $this->jumlah_jam,
            'tahun_ajaran' => $this->tahun_ajaran,
            'deskripsi' => filled($this->deskripsi) ? (string) $this->deskripsi : null,

            // Modul unggahan: berkasnya yang jadi isinya. Dialirkan lewat
            // portal, bukan lewat alamat Kurikulum, supaya haknya diperiksa
            // dan token siswa tidak pernah pergi ke aplikasi lain.
            'berkas_url' => $this->fileTersedia() ? route('api.berkas.modul-ajar', $this->id) : null,
            'berkas_nama' => $this->fileTersedia() ? (string) ($this->file_nama ?: basename((string) $this->file)) : null,
            'berkas_ukuran' => $this->fileTersedia() ? $this->ukuranFileManusiawi() : null,

            'bagian' => $this->bagian(),
        ];
    }

    /**
     * Bagian isi yang ada isinya, urut seperti ModulAjar::BAGIAN_SISWA.
     *
     * @return list<array{kunci: string, label: string, html: string}>
     */
    private function bagian(): array
    {
        $bagian = [];

        foreach (ModulAjar::BAGIAN_SISWA as $kunci => $label) {
            $html = TeksKaya::html($this->{$kunci});

            if (trim(strip_tags($html)) === '') {
                continue;
            }

            $bagian[] = ['kunci' => $kunci, 'label' => $label, 'html' => $html];
        }

        return $bagian;
    }
}
