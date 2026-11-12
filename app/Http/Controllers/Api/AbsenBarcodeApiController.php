<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Support\AbsensiBarcode;
use App\Support\RekapAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Absen masuk/pulang dengan memindai QR Layar Tap aplikasi Absensi.
 *
 * Pembagian tugasnya sama persis dengan halaman web, dan itu yang penting:
 * aplikasi ponsel hanya mengirimkan hasil pindaian, portal memastikan siapa
 * yang login, lalu aplikasi Absensi yang menuliskan absensinya. Jadi kunci API
 * absensi tidak pernah ikut terbungkus di dalam APK, dan hasilnya sama dengan
 * tap kartu RFID — termasuk jeda antar-tap.
 */
class AbsenBarcodeApiController extends Controller
{
    public function __construct(private AbsensiBarcode $barcode) {}

    /** Keadaan menu absen hari ini, dipanggil sebelum kamera dibuka. */
    public function show(Request $request, RekapAbsensi $rekapAbsensi): JsonResponse
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $hariIni = $rekapAbsensi->laporan($siswa, $siswa->rombelAktif()?->id, today(), today())['rows'][0] ?? null;

        return response()->json([
            'aktif' => $this->barcode->aktif(),
            'hari_ini' => $hariIni ? [
                'tanggal' => $hariIni['tanggal']->toDateString(),
                'jam_masuk' => $hariIni['jam_masuk'],
                'jam_pulang' => $hariIni['jam_pulang'],
                'status' => $hariIni['status'],
                'keterangan' => $hariIni['keterangan'],
            ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ], [], [
            'token' => 'Barcode',
        ]);

        // Isi QR bisa berupa alamat portal dengan token di query, atau token
        // mentah; keduanya diterima, selain itu ditolak sebelum menghubungi
        // aplikasi Absensi.
        $token = AbsensiBarcode::ambilToken($data['token']);

        if ($token === null) {
            return response()->json([
                'ok' => false,
                'kode' => 'bukan_barcode_absensi',
                'pesan' => 'Barcode tidak dikenali. Pindai barcode pada layar absensi sekolah.',
            ], 422);
        }

        $hasil = $this->barcode->absen($siswa, $token);

        if (! ($hasil['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'kode' => $hasil['kode'] ?? 'gagal',
                'pesan' => $hasil['pesan'] ?? 'Absen gagal dicatat.',
            ], 422);
        }

        // "ulang" bukan kegagalan, tetapi juga tidak tercatat — dibedakan dari
        // absen yang benar-benar masuk supaya siswa tidak mengira sudah selesai.
        return response()->json([
            'ok' => true,
            'tercatat' => ($hasil['jenis'] ?? '') !== 'ulang',
            'jenis' => $hasil['jenis'] ?? null,
            'jam' => $hasil['jam'] ?? null,
            'terlambat' => (bool) ($hasil['terlambat'] ?? false),
            'bisa_lagi' => $hasil['bisa_lagi'] ?? null,
            'tap_terakhir' => $hasil['tap_terakhir'] ?? null,
            'pesan' => $this->pesan($hasil),
        ]);
    }

    /**
     * Kalimat siap tampil, disusun sama dengan halaman web supaya siswa yang
     * berpindah dari peramban ke aplikasi tidak menemukan istilah baru.
     *
     * @param  array<string, mixed>  $hasil
     */
    private function pesan(array $hasil): string
    {
        $jam = substr((string) ($hasil['jam'] ?? ''), 0, 5);

        if (($hasil['jenis'] ?? '') === 'ulang') {
            $terakhir = substr((string) ($hasil['tap_terakhir'] ?? ''), 0, 5);
            $bisaLagi = substr((string) ($hasil['bisa_lagi'] ?? ''), 0, 5);

            $pesan = $terakhir !== ''
                ? "Absen kamu pukul {$terakhir} sudah tercatat, jadi pindaian ini belum dihitung."
                : 'Kamu baru saja absen, jadi pindaian ini belum dihitung.';

            return $bisaLagi !== ''
                ? $pesan." Pindai lagi mulai pukul {$bisaLagi} untuk absen berikutnya."
                : $pesan.' Tunggu beberapa menit sebelum memindai lagi.';
        }

        if (($hasil['jenis'] ?? '') === 'pulang') {
            return "Absen pulang tercatat pukul {$jam}. Hati-hati di jalan!";
        }

        return ($hasil['terlambat'] ?? false)
            ? "Absen masuk tercatat pukul {$jam}, tetapi melewati batas jam masuk (terlambat)."
            : "Absen masuk tercatat pukul {$jam}. Selamat belajar!";
    }
}
