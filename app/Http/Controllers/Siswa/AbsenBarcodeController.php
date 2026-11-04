<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Support\AbsensiBarcode;
use App\Support\RekapAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Absen masuk/pulang dengan memindai QR yang berganti tiap 2 menit di Layar Tap
 * aplikasi Absensi. Portal hanya memastikan siapa yang login lalu meneruskan
 * token pindaian ke aplikasi Absensi; aplikasi itu yang menulis absensinya.
 *
 * Dua jalan masuk, keduanya berujung di halaman yang sama:
 *  - QR dipindai aplikasi kamera HP  -> membuka halaman ini dengan ?token=...
 *  - QR dipindai dari halaman ini    -> kamera di dalam portal (butuh HTTPS)
 */
class AbsenBarcodeController extends Controller
{
    public function __construct(private AbsensiBarcode $barcode) {}

    public function index(Request $request, RekapAbsensi $rekapAbsensi): View
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $hasilPindai = (string) $request->query('token', '');
        $token = AbsensiBarcode::ambilToken($hasilPindai);
        $aktif = $this->barcode->aktif();

        return view('siswa.absen', [
            'siswa' => $siswa,
            'kelas' => $siswa->rombelAktif()?->nama_rombel,
            'aktif' => $aktif,
            'token' => $token,
            // Pindaian yang bukan barcode absensi, mis. QR milik aplikasi lain.
            'pindaiSalah' => $token === null && $hasilPindai !== '',
            'verifikasi' => ($token !== null && $aktif) ? $this->barcode->verifikasi($token) : null,
            'hariIni' => $rekapAbsensi->laporan($siswa, $siswa->rombelAktif()?->id, today(), today())['rows'][0] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ], [], [
            'token' => 'Barcode',
        ]);

        $token = AbsensiBarcode::ambilToken($data['token']);

        if ($token === null) {
            return redirect()->route('siswa.absen')
                ->with('error', 'Barcode tidak dikenali. Pindai barcode pada layar absensi sekolah.');
        }

        $hasil = $this->barcode->absen($siswa, $token);

        if (! ($hasil['ok'] ?? false)) {
            return redirect()->route('siswa.absen')->with('error', $hasil['pesan'] ?? 'Absen gagal dicatat.');
        }

        // "ulang" bukan kegagalan, tetapi juga tidak tercatat — dibedakan dari
        // absen yang benar-benar masuk supaya siswa tidak mengira sudah selesai.
        if (($hasil['jenis'] ?? '') === 'ulang') {
            return redirect()->route('siswa.absen')->with('warning', $this->pesanJeda($hasil));
        }

        return redirect()->route('siswa.absen')->with('success', $this->pesanBerhasil($hasil));
    }

    /**
     * @param  array{jenis?: string, jam?: string, terlambat?: bool}  $hasil
     */
    private function pesanBerhasil(array $hasil): string
    {
        $jam = substr((string) ($hasil['jam'] ?? ''), 0, 5);

        return ($hasil['jenis'] ?? '') === 'pulang'
            ? "Absen pulang tercatat pukul {$jam}. Hati-hati di jalan!"
            : (($hasil['terlambat'] ?? false)
                ? "Absen masuk tercatat pukul {$jam}, tetapi melewati batas jam masuk (terlambat)."
                : "Absen masuk tercatat pukul {$jam}. Selamat belajar!");
    }

    /**
     * Pindaian yang masih dalam jeda tap aplikasi Absensi. Jeda ini juga berlaku
     * untuk kartu RFID: absen pulang baru bisa dicatat setelah jendelanya lewat.
     *
     * @param  array{tap_terakhir?: string, bisa_lagi?: string, jeda_menit?: int}  $hasil
     */
    private function pesanJeda(array $hasil): string
    {
        $terakhir = substr((string) ($hasil['tap_terakhir'] ?? ''), 0, 5);
        $bisaLagi = substr((string) ($hasil['bisa_lagi'] ?? ''), 0, 5);

        $pesan = $terakhir !== ''
            ? "Absen kamu pukul {$terakhir} sudah tercatat, jadi pindaian ini belum dihitung."
            : 'Kamu baru saja absen, jadi pindaian ini belum dihitung.';

        return $bisaLagi !== ''
            ? $pesan." Pindai lagi mulai pukul {$bisaLagi} untuk absen berikutnya."
            : $pesan.' Tunggu beberapa menit sebelum memindai lagi.';
    }
}
