<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MateriResource;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\MateriOnline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Materi online untuk kelas siswa (data aplikasi Kurikulum). Query dan
 * pencariannya sama dengan menu Materi Online di peramban.
 */
class MateriApiController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
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

        return MateriResource::collection($materi);
    }
}
