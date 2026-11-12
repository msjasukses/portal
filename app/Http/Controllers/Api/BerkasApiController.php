<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\BukuDigital;
use App\Models\Kurikulum\JurnalPkl;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengunduh file dari storage aplikasi Kurikulum untuk aplikasi ponsel.
 *
 * Pemeriksaan haknya sama dengan BerkasController di peramban: file kelas lain
 * dan milik siswa lain diperlakukan seolah tidak ada. Bedanya hanya satu —
 * yang membuktikan siapa pemintanya adalah token bearer, bukan sesi.
 *
 * Semuanya dikirim sebagai unduhan. Aplikasi yang menyimpan lalu membuka
 * filenya sendiri tidak pernah menjalankan isinya di dalam portal, jadi
 * unggahan HTML milik siapa pun tidak bisa meminjam alamat portal.
 */
class BerkasApiController extends Controller
{
    public function materi(Request $request, MateriOnline $materi): StreamedResponse
    {
        $this->pastikanKelasSiswa($request->user('siswa'), $materi->kelas_id);

        return $this->kirim($materi->file);
    }

    public function tugas(Request $request, Tugas $tugas): StreamedResponse
    {
        $this->pastikanKelasSiswa($request->user('siswa'), $tugas->kelas_id);

        return $this->kirim($tugas->file_lampiran);
    }

    public function pengumpulan(Request $request, PengumpulanTugas $pengumpulan): StreamedResponse
    {
        abort_unless((int) $pengumpulan->siswa_id === (int) $request->user('siswa')->id, 404);

        return $this->kirim($pengumpulan->file);
    }

    public function jurnal(Request $request, JurnalPkl $jurnal): StreamedResponse
    {
        abort_unless((int) $jurnal->siswa_id === (int) $request->user('siswa')->id, 404);

        return $this->kirim($jurnal->foto);
    }

    /**
     * Berkas buku katalog digital.
     *
     * Katalog buku bukan milik satu kelas: seluruh siswa berhak membacanya,
     * sama seperti di peramban. Yang dijaga jenisnya — hanya PDF yang dibaca
     * lewat portal; audio dan buku interaktif tetap dibuka di SIBI.
     */
    public function buku(BukuDigital $buku): StreamedResponse
    {
        abort_unless($buku->tipe === 'pdf', 404, 'Buku ini bukan berkas PDF.');

        return $this->kirim($buku->file_path);
    }

    public function bukuCover(BukuDigital $buku): StreamedResponse
    {
        return $this->kirim($buku->cover_path);
    }

    private function pastikanKelasSiswa(Siswa $siswa, mixed $kelasId): void
    {
        abort_unless(in_array((int) $kelasId, $siswa->semuaRombelId(), true), 404);
    }

    private function kirim(?string $path): StreamedResponse
    {
        $disk = Storage::disk('kurikulum');
        abort_unless($path && $disk->exists($path), 404, 'File tidak ditemukan di aplikasi Kurikulum.');

        return $disk->download($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
    }
}
