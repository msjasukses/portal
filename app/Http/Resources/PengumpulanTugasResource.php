<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\PengumpulanTugas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jawaban siswa atas satu tugas, termasuk nilai bila sudah diperiksa guru.
 *
 * @mixin PengumpulanTugas
 */
class PengumpulanTugasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catatan' => $this->catatan,
            'berkas_url' => $this->file ? route('api.berkas.pengumpulan', $this->id) : null,
            'dikumpulkan_pada' => $this->dikumpulkan_pada?->toIso8601String(),
            'terlambat' => (bool) $this->terlambat,
            'nilai' => $this->nilai !== null ? (int) $this->nilai : null,
            'umpan_balik' => $this->umpan_balik,
            'dinilai_pada' => $this->dinilai_pada?->toIso8601String(),
            'sudah_dinilai' => $this->sudahDinilai(),
        ];
    }
}
