<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Kesiswaan\Pelanggaran;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Info pelanggaran siswa beserta total poinnya (data aplikasi Kesiswaan).
 */
class PelanggaranController extends Controller
{
    public function __invoke(Request $request): View
    {
        $pelanggaran = Pelanggaran::with('dataPelanggaran.jenisPelanggaran')
            ->where('siswa_id', $request->user('siswa')->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return view('siswa.pelanggaran', [
            'pelanggaran' => $pelanggaran,
            'totalPoin' => $pelanggaran->sum('poin'),
        ]);
    }
}
