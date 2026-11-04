<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\MateriOnline;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Info materi online (data aplikasi Kurikulum). Sama dengan menu Materi Online
 * saat login sebagai siswa di Kurikulum: materi untuk kelas siswa pada tahun
 * ajaran aktif, pencarian, kolom Isi Materi, tombol File & Video, 10 per halaman.
 */
class MateriController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $cari = trim((string) $request->query('q'));

        $materi = MateriOnline::with(['mataPelajaran', 'kelas', 'guru'])
            ->where('kelas_id', $siswa->rombelAktif()?->id ?? 0)
            ->when($cari !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('judul', 'like', '%'.$cari.'%')
                ->orWhere('link_video', 'like', '%'.$cari.'%')
                ->orWhere('deskripsi', 'like', '%'.$cari.'%')))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('siswa.materi', [
            'materi' => $materi,
            'cari' => $cari,
        ]);
    }
}
