<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaResource;
use App\Http\Resources\MateriResource;
use App\Http\Resources\SiswaResource;
use App\Http\Resources\TugasResource;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use App\Models\Kurikulum\AgendaMengajar;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use App\Support\RekapAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ringkasan untuk layar pertama aplikasi.
 *
 * Tiap bagian dibungkus rescue() seperti dashboard di peramban: satu aplikasi
 * sekolah yang databasenya mati hanya mengosongkan bagiannya sendiri, bukan
 * menggagalkan seluruh layar. Bagian yang kosong dikirim sebagai null, supaya
 * aplikasi bisa membedakan "belum ada datanya" dari "tidak bisa dimuat".
 */
class DashboardApiController extends Controller
{
    public function __invoke(Request $request, RekapAbsensi $rekapAbsensi): JsonResponse
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $rombelId = $siswa->rombelAktif()?->id;
        [$dari, $sampai] = RekapAbsensi::periodeBulan(today(), TahunAjaran::aktif());

        return response()->json([
            'siswa' => new SiswaResource($siswa),
            'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'label_absensi' => RekapAbsensi::LABEL,
            'rekap_absensi' => rescue(fn () => $rekapAbsensi->laporan($siswa, $rombelId, $dari, $sampai)['rekap']),
            'agenda' => rescue(fn () => AgendaResource::collection(
                AgendaMengajar::with(['guru', 'mataPelajaran'])
                    ->untukKelas($rombelId)
                    ->latest('waktu_pengisian')
                    ->limit(5)
                    ->get()
            )->toArray($request)),
            'tugas' => rescue(fn () => $this->ringkasanTugas($request, $siswa, $rombelId)),
            'materi' => rescue(fn () => MateriResource::collection(
                MateriOnline::with(['mataPelajaran', 'guru'])
                    ->where('kelas_id', $rombelId ?? 0)
                    ->latest('id')
                    ->limit(5)
                    ->get()
            )->toArray($request)),
        ]);
    }

    /**
     * Lima tugas terbaru beserta status pengumpulannya, dan ringkasan dari
     * seluruh tugas kelas (sama dengan dashboard siswa di peramban).
     *
     * @return array{terbaru: list<mixed>, ringkasan: array{belum: int, dikumpulkan: int, dinilai: int}}
     */
    private function ringkasanTugas(Request $request, Siswa $siswa, ?int $rombelId): array
    {
        $semuaTugasId = Tugas::where('kelas_id', $rombelId ?? 0)->pluck('id');
        $pengumpulan = PengumpulanTugas::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $semuaTugasId)
            ->get()
            ->keyBy('tugas_id');

        $terbaru = TugasResource::collection(
            Tugas::with('mataPelajaran')->where('kelas_id', $rombelId ?? 0)->latest()->limit(5)->get()
        );

        $terbaru->collection->each(
            fn (TugasResource $resource) => $resource->setPengumpulan($pengumpulan->get($resource->resource->id)),
        );

        return [
            'terbaru' => $terbaru->toArray($request),
            'ringkasan' => [
                'belum' => $semuaTugasId->count() - $pengumpulan->count(),
                'dikumpulkan' => $pengumpulan->count(),
                'dinilai' => $pengumpulan->filter(fn (PengumpulanTugas $item) => $item->sudahDinilai())->count(),
            ],
        ];
    }
}
