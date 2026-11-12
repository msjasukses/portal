<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrestasiResource;
use App\Models\Kesiswaan\Prestasi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Prestasi siswa beserta rekap per bidang (data aplikasi Kesiswaan).
 */
class PrestasiApiController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $prestasi = Prestasi::where('siswa_id', $request->user('siswa')->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return PrestasiResource::collection($prestasi)->additional([
            'rekap' => [
                'jumlah' => $prestasi->count(),
                'per_bidang' => $prestasi->countBy('bidang'),
            ],
        ]);
    }
}
