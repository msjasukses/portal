<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use App\Models\Kurikulum\AgendaMengajar;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use App\Support\RekapAbsensi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard siswa: rekap absensi bulan berjalan (aplikasi Absensi) serta
 * agenda mengajar, tugas, dan materi online terbaru (aplikasi Kurikulum).
 * Tiap bagian dimuat terpisah, jadi satu aplikasi yang tidak bisa dihubungi
 * tidak membuat seluruh dashboard gagal.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, RekapAbsensi $rekapAbsensi): View
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $rombel = $siswa->rombelAktif();
        $rombelId = $rombel?->id;
        [$dari, $sampai] = RekapAbsensi::periodeBulan(today(), TahunAjaran::aktif());

        return view('siswa.dashboard', [
            'siswa' => $siswa,
            'rombel' => $rombel,
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'rekapAbsensi' => rescue(fn () => $rekapAbsensi->laporan($siswa, $rombelId, $dari, $sampai)['rekap']),
            'agenda' => rescue(fn () => AgendaMengajar::with(['guru', 'mataPelajaran'])
                ->untukKelas($rombelId)
                ->latest('waktu_pengisian')
                ->limit(5)
                ->get()),
            'tugas' => rescue(fn () => $this->ringkasanTugas($siswa, $rombelId)),
            'materi' => rescue(fn () => MateriOnline::with(['mataPelajaran', 'guru'])
                ->where('kelas_id', $rombelId ?? 0)
                ->latest('id')
                ->limit(5)
                ->get()),
        ]);
    }

    /**
     * Lima tugas terbaru beserta status pengumpulannya, dan ringkasan dari
     * seluruh tugas kelas (sama dengan dashboard siswa di Kurikulum).
     *
     * @return array{terbaru: Collection<int, Tugas>, pengumpulan: Collection<int, PengumpulanTugas>, ringkasan: array{belum: int, dikumpulkan: int, dinilai: int}}
     */
    private function ringkasanTugas(Siswa $siswa, ?int $rombelId): array
    {
        $semuaTugasId = Tugas::where('kelas_id', $rombelId ?? 0)->pluck('id');
        $pengumpulan = PengumpulanTugas::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $semuaTugasId)
            ->get()
            ->keyBy('tugas_id');

        return [
            'terbaru' => Tugas::with('mataPelajaran')->where('kelas_id', $rombelId ?? 0)->latest()->limit(5)->get(),
            'pengumpulan' => $pengumpulan,
            'ringkasan' => [
                'belum' => $semuaTugasId->count() - $pengumpulan->count(),
                'dikumpulkan' => $pengumpulan->count(),
                'dinilai' => $pengumpulan->filter(fn (PengumpulanTugas $item) => $item->sudahDinilai())->count(),
            ],
        ];
    }
}
