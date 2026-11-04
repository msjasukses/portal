<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Kesiswaan\Prestasi;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Info prestasi siswa (data aplikasi Kesiswaan).
 */
class PrestasiController extends Controller
{
    public function __invoke(Request $request): View
    {
        $prestasi = Prestasi::where('siswa_id', $request->user('siswa')->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return view('siswa.prestasi', [
            'prestasi' => $prestasi,
            'perBidang' => $prestasi->countBy('bidang'),
        ]);
    }
}
