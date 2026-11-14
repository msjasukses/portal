<?php

namespace App\Http\Resources;

use App\Models\Kurikulum\AgendaMengajar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu jam pelajaran pada agenda mengajar guru, beserta status kehadiran
 * siswa yang sedang login pada jam itu.
 *
 * @mixin AgendaMengajar
 */
class AgendaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'waktu' => $this->waktu_pengisian?->toIso8601String(),
            'jam_ke' => $this->jam_ke,
            'jumlah_jam' => (int) $this->jumlah_jam,
            'mapel' => $this->mataPelajaran?->nama_mapel,
            'guru' => $this->guru?->nama_ptk,
            'materi' => $this->materi,
            'catatan' => $this->catatan,
            'status' => $this->status,
            'kehadiran' => $this->kehadiranSiswa((int) $request->user('siswa')->id),

            // Hanya secukupnya untuk dipajang dan diketuk. Rinciannya diambil
            // dari endpoint tersendiri, sebab satu modul bisa berisi belasan
            // bagian teks kaya - memuatnya untuk 15 baris agenda berarti
            // mengirim puluhan kilobita yang hampir seluruhnya tidak dibaca.
            'modul' => $this->whenLoaded('modulAjar', fn () => $this->modulAjar ? [
                'id' => $this->modulAjar->id,
                'judul' => (string) $this->modulAjar->judul,
                'pertemuan_ke' => $this->modulAjar->pertemuan_ke,
            ] : null),
        ];
    }
}
