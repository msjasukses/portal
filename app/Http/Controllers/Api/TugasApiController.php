<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TugasResource;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * Tugas kelas dan pengumpulan jawabannya (data aplikasi Kurikulum).
 *
 * Aturannya sama dengan menu Tugas di peramban: jawaban boleh diubah selama
 * belum dinilai guru, pengumpulan lewat batas tetap diterima tetapi ditandai
 * terlambat, dan filenya disimpan di storage Kurikulum supaya guru langsung
 * melihatnya di aplikasi Kurikulum — bukan di folder milik portal.
 */
class TugasApiController extends Controller
{
    /** Folder file jawaban, sama dengan aplikasi Kurikulum. */
    private const FOLDER = 'uploads/pengumpulan_tugas';

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $cari = trim((string) $request->query('q'));

        $tugas = Tugas::with(['mataPelajaran', 'guru'])
            ->where('kelas_id', $siswa->rombelAktif()?->id ?? 0)
            ->when($cari !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('judul', 'like', '%'.$cari.'%')
                ->orWhere('deskripsi', 'like', '%'.$cari.'%')))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Satu query untuk seluruh halaman, bukan satu tambahan per tugas.
        $pengumpulan = PengumpulanTugas::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $tugas->pluck('id'))
            ->get()
            ->keyBy('tugas_id');

        $daftar = TugasResource::collection($tugas);

        $daftar->collection->each(
            fn (TugasResource $resource) => $resource->setPengumpulan($pengumpulan->get($resource->resource->id)),
        );

        return $daftar;
    }

    public function show(Request $request, Tugas $tugas): TugasResource
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $this->pastikanTugasKelasSiswa($tugas, $siswa);

        return (new TugasResource($tugas->load(['mataPelajaran', 'kelas', 'guru'])))->setPengumpulan(
            PengumpulanTugas::where('tugas_id', $tugas->id)->where('siswa_id', $siswa->id)->first(),
        );
    }

    public function store(Request $request, Tugas $tugas): TugasResource|JsonResponse
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $this->pastikanTugasKelasSiswa($tugas, $siswa);

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,jpg,jpeg,png'],
        ], [], [
            'catatan' => 'Catatan/Jawaban',
            'file' => 'File Jawaban',
        ]);

        $pengumpulan = PengumpulanTugas::firstOrNew([
            'tugas_id' => $tugas->id,
            'siswa_id' => $siswa->id,
        ]);

        if ($pengumpulan->sudahDinilai()) {
            return response()->json([
                'pesan' => 'Tugas ini sudah dinilai guru, jawabannya tidak bisa diubah lagi.',
            ], 422);
        }

        if (blank($data['catatan'] ?? null) && ! $request->hasFile('file') && ! $pengumpulan->exists) {
            return response()->json([
                'pesan' => 'Isi catatan jawaban atau unggah file terlebih dahulu.',
            ], 422);
        }

        if ($request->hasFile('file')) {
            if ($pengumpulan->file) {
                Storage::disk('kurikulum')->delete($pengumpulan->file);
            }

            $pengumpulan->file = $request->file('file')->store(self::FOLDER, 'kurikulum');
        }

        $pengumpulan->catatan = $data['catatan'] ?? null;
        $pengumpulan->dikumpulkan_pada = now();
        $pengumpulan->terlambat = $tugas->lewatBatas();
        $pengumpulan->save();

        return (new TugasResource($tugas))
            ->setPengumpulan($pengumpulan)
            ->additional(['pesan' => $pengumpulan->terlambat
                ? 'Tugas berhasil dikumpulkan, tetapi tercatat melewati batas waktu.'
                : 'Tugas berhasil dikumpulkan.',
            ]);
    }

    /** Siswa hanya boleh membuka & mengumpulkan tugas untuk kelasnya sendiri. */
    private function pastikanTugasKelasSiswa(Tugas $tugas, Siswa $siswa): void
    {
        abort_unless(in_array((int) $tugas->kelas_id, $siswa->semuaRombelId(), true), 403, 'Tugas ini bukan untuk kelas Anda.');
    }
}
