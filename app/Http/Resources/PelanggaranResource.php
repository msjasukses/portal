<?php

namespace App\Http\Resources;

use App\Models\Kesiswaan\Pelanggaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu catatan pelanggaran siswa beserta butir dan kelompoknya.
 *
 * Poin diambil dari kolom pelanggaran.poin, bukan dari master butirnya: poin
 * master bisa berubah kemudian, sedangkan yang sudah tercatat tidak boleh ikut
 * berubah di belakang siswa.
 *
 * @mixin Pelanggaran
 */
class PelanggaranResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal?->toDateString(),
            'butir' => $this->dataPelanggaran?->nama,
            'jenis' => $this->dataPelanggaran?->jenisPelanggaran?->nama,
            'poin' => (int) $this->poin,
            'keterangan' => $this->keterangan,
            'tindak_lanjut' => $this->tindak_lanjut,
        ];
    }
}
