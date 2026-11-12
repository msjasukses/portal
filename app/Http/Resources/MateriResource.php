<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\MateriOnline;
use App\Support\TeksKaya;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Materi online untuk kelas siswa.
 *
 * Deskripsinya dikirim sebagai HTML yang sudah disaring — isinya berasal dari
 * editor teks kaya aplikasi Kurikulum, jadi penyaringannya dijalankan ulang di
 * sini, sama seperti saat ditampilkan di peramban.
 *
 * @mixin MateriOnline
 */
class MateriResource extends JsonResource
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
            'guru' => $this->guru?->nama_ptk,
            'deskripsi_html' => TeksKaya::html($this->deskripsi),
            'berkas_url' => $this->file ? route('api.berkas.materi', $this->id) : null,
            'video_url' => $this->tautanVideo(),
            'penanda' => $this->penanda(),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
