<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use App\Support\TeksKaya;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu tugas kelas beserta jawaban siswa atasnya.
 *
 * Jawabannya disuntikkan lewat setPengumpulan() supaya daftar tugas cukup
 * sekali menanyakan jawaban siswa untuk seluruh halaman — bukan satu query
 * tambahan per tugas.
 *
 * @mixin Tugas
 */
class TugasResource extends JsonResource
{
    private ?PengumpulanTugas $pengumpulan = null;

    private bool $pengumpulanDisetel = false;

    public function setPengumpulan(?PengumpulanTugas $pengumpulan): self
    {
        $this->pengumpulan = $pengumpulan;
        $this->pengumpulanDisetel = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pengumpulan = $this->pengumpulanDisetel
            ? $this->pengumpulan
            : PengumpulanTugas::where('tugas_id', $this->id)
                ->where('siswa_id', $request->user('siswa')->id)
                ->first();

        return [
            'id' => $this->id,
            'judul' => (string) $this->judul,
            'mapel' => $this->mataPelajaran?->nama_mapel,
            'guru' => $this->guru?->nama_ptk,
            'deskripsi_html' => TeksKaya::html($this->deskripsi),
            'lampiran_url' => $this->file_lampiran ? route('api.berkas.tugas', $this->id) : null,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'lewat_batas' => $this->lewatBatas(),
            'pengumpulan' => $pengumpulan ? new PengumpulanTugasResource($pengumpulan) : null,
        ];
    }
}
