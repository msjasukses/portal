<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PelanggaranResource;
use App\Models\Kesiswaan\Pelanggaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Pelanggaran siswa beserta total poinnya (data aplikasi Kesiswaan).
 */
class PelanggaranApiController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $pelanggaran = Pelanggaran::with('dataPelanggaran.jenisPelanggaran')
            ->where('siswa_id', $request->user('siswa')->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return PelanggaranResource::collection($pelanggaran)->additional([
            'rekap' => [
                'jumlah' => $pelanggaran->count(),
                'total_poin' => (int) $pelanggaran->sum('poin'),
            ],
        ]);
    }
}
