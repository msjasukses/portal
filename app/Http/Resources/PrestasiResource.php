<?php

namespace App\Http\Resources;

use App\Models\Kesiswaan\Prestasi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu prestasi siswa (data aplikasi Kesiswaan).
 *
 * @mixin Prestasi
 */
class PrestasiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => (string) $this->nama_prestasi,
            'bidang' => $this->bidang,
            'tingkat' => $this->tingkat,
            'peringkat' => $this->peringkat,
            'penyelenggara' => $this->penyelenggara,
            'tanggal' => $this->tanggal?->toDateString(),
            'keterangan' => $this->keterangan,
        ];
    }
}
