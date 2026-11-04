<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membuka file dari storage aplikasi Kurikulum setelah memastikan file itu
 * boleh dilihat siswa yang login. Hanya PDF & gambar yang tampil langsung di
 * browser; jenis lain (mis. HTML unggahan) selalu diunduh agar tidak berjalan
 * di halaman portal.
 */
class BerkasController extends Controller
{
    private const TAMPIL_LANGSUNG = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

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

    private function pastikanKelasSiswa(Siswa $siswa, mixed $kelasId): void
    {
        abort_unless(in_array((int) $kelasId, $siswa->semuaRombelId(), true), 404);
    }

    private function kirim(?string $path): StreamedResponse
    {
        $disk = Storage::disk('kurikulum');
        abort_unless($path && $disk->exists($path), 404, 'File tidak ditemukan di aplikasi Kurikulum.');

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        return in_array($disk->mimeType($path), self::TAMPIL_LANGSUNG, true)
            ? $disk->response($path, basename($path), $headers)
            : $disk->download($path, basename($path), $headers);
    }
}
