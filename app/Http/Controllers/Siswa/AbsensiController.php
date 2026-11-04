<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use App\Support\RekapAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Info absensi bulanan siswa (data aplikasi Absensi) pada tahun ajaran aktif.
 */
class AbsensiController extends Controller
{
    public function __invoke(Request $request, RekapAbsensi $rekapAbsensi): View
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $tahunAjaran = TahunAjaran::aktif();
        $daftarBulan = $this->daftarBulan($tahunAjaran);

        // Bulan di luar daftar (mis. diketik manual) jatuh ke bulan terbaru.
        $bulan = collect($daftarBulan)->first(fn (CarbonImmutable $item) => $item->format('Y-m') === $request->query('bulan'))
            ?? $daftarBulan[0];

        [$dari, $sampai] = RekapAbsensi::periodeBulan($bulan, $tahunAjaran);

        return view('siswa.absensi', [
            'daftarBulan' => $daftarBulan,
            'bulan' => $bulan,
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'laporan' => $rekapAbsensi->laporan($siswa, $siswa->rombelAktif()?->id, $dari, $sampai),
        ]);
    }

    /**
     * Bulan yang bisa dipilih: dari awal tahun ajaran aktif sampai bulan ini
     * (atau akhir tahun ajaran), terbaru lebih dulu. Tanpa tanggal tahun
     * ajaran, dipakai 12 bulan terakhir.
     *
     * @return non-empty-list<CarbonImmutable>
     */
    private function daftarBulan(?TahunAjaran $tahunAjaran): array
    {
        $akhir = CarbonImmutable::today()->startOfMonth();

        if ($tahunAjaran?->tanggal_selesai) {
            $akhir = $akhir->min(CarbonImmutable::parse($tahunAjaran->tanggal_selesai)->startOfMonth());
        }

        $awal = $tahunAjaran?->tanggal_mulai
            ? CarbonImmutable::parse($tahunAjaran->tanggal_mulai)->startOfMonth()
            : $akhir->subMonths(11);

        $daftar = [];

        for ($bulan = $akhir; $bulan->gte($awal); $bulan = $bulan->subMonth()) {
            $daftar[] = $bulan;
        }

        return $daftar ?: [$akhir];
    }
}
