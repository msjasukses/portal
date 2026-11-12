<?php

namespace App\Http\Resources;

use App\Models\Datacenter\Siswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identitas siswa yang login. Hanya kolom yang perlu ditampilkan aplikasi —
 * data induk lainnya tetap di Data Center dan tidak ikut dikirim.
 *
 * @mixin Siswa
 */
class SiswaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rombel = $this->rombelAktif();

        return [
            'id' => $this->id,
            'nisn' => (string) $this->nisn,
            'nis' => $this->nis ? (string) $this->nis : null,
            'nama' => (string) $this->nama_siswa,
            'jenis_kelamin' => $this->jenis_kelamin,
            'email' => $this->email,
            'kelas' => $rombel ? [
                'id' => (int) $rombel->id,
                'nama' => (string) $rombel->nama_rombel,
                'tingkat' => (int) $rombel->tingkat,
            ] : null,
        ];
    }
}
