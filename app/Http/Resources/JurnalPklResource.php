<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\JurnalPkl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu hari jurnal PKL/magang siswa.
 *
 * @mixin JurnalPkl
 */
class JurnalPklResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal?->toDateString(),
            'tempat' => $this->tempat,
            'jam_mulai' => $this->jam($this->jam_mulai),
            'jam_selesai' => $this->jam($this->jam_selesai),
            'kegiatan' => $this->kegiatan,
            'hasil' => $this->hasil,
            'kendala' => $this->kendala,
            'foto_url' => $this->foto ? route('api.berkas.jurnal', $this->id) : null,
            'status' => $this->status,
            'catatan_pembimbing' => $this->catatan_pembimbing,
            'diperiksa_pada' => $this->diperiksa_pada?->toIso8601String(),
            'bisa_diubah' => $this->bisaDiubahSiswa(),
        ];
    }

    /** Kolom TIME datang sebagai "07:00:00"; aplikasi hanya perlu jam dan menitnya. */
    private function jam(?string $nilai): ?string
    {
        return $nilai ? substr($nilai, 0, 5) : null;
    }
}
