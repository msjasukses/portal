<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use App\Support\RekapAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Absensi bulanan siswa pada tahun ajaran aktif (data aplikasi Absensi).
 *
 * Perhitungannya diserahkan seluruhnya kepada RekapAbsensi — kelas yang sama
 * yang dipakai halaman web — supaya angka di aplikasi tidak pernah berbeda
 * dari angka yang dilihat guru.
 */
class AbsensiApiController extends Controller
{
    public function __invoke(Request $request, RekapAbsensi $rekapAbsensi): JsonResponse
    {
        $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
        ], [], [
            'bulan' => 'Bulan',
        ]);

        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $tahunAjaran = TahunAjaran::aktif();
        $daftarBulan = $this->daftarBulan($tahunAjaran);

        // Bulan di luar daftar (mis. diketik manual) jatuh ke bulan terbaru.
        $bulan = collect($daftarBulan)->first(fn (CarbonImmutable $item) => $item->format('Y-m') === $request->query('bulan'))
            ?? $daftarBulan[0];

        [$dari, $sampai] = RekapAbsensi::periodeBulan($bulan, $tahunAjaran);
        $laporan = $rekapAbsensi->laporan($siswa, $siswa->rombelAktif()?->id, $dari, $sampai);

        return response()->json([
            'bulan' => $bulan->format('Y-m'),
            'daftar_bulan' => array_map(fn (CarbonImmutable $item) => $item->format('Y-m'), $daftarBulan),
            'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'label' => RekapAbsensi::LABEL,
            'rekap' => $laporan['rekap'],
            'hari' => array_map(fn (array $baris) => [
                'tanggal' => $baris['tanggal']->toDateString(),
                'jam_masuk' => $baris['jam_masuk'],
                'jam_pulang' => $baris['jam_pulang'],
                'status' => $baris['status'],
                'keterangan' => $baris['keterangan'],
                'shift' => $baris['shift'],
            ], $laporan['rows']),
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
