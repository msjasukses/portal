<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Info tugas & pengumpulan tugas (data aplikasi Kurikulum). Aturannya sama
 * dengan menu Tugas siswa di Kurikulum: tugas untuk kelas siswa, jawaban bisa
 * diubah selama belum dinilai, dan pengumpulan lewat batas ditandai terlambat.
 * File jawaban disimpan di storage Kurikulum agar langsung terlihat oleh guru.
 */
class TugasController extends Controller
{
    /** Folder file jawaban, sama dengan aplikasi Kurikulum. */
    private const FOLDER = 'uploads/pengumpulan_tugas';

    public function index(Request $request): View
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

        return view('siswa.tugas.index', [
            'tugas' => $tugas,
            'pengumpulan' => PengumpulanTugas::where('siswa_id', $siswa->id)
                ->whereIn('tugas_id', $tugas->pluck('id'))
                ->get()
                ->keyBy('tugas_id'),
            'cari' => $cari,
        ]);
    }

    public function show(Request $request, Tugas $tugas): View
    {
        /** @var Siswa $siswa */
        $siswa = $request->user('siswa');
        $this->pastikanTugasKelasSiswa($tugas, $siswa);

        return view('siswa.tugas.show', [
            'tugas' => $tugas->load(['mataPelajaran', 'kelas', 'guru']),
            'pengumpulan' => PengumpulanTugas::where('tugas_id', $tugas->id)->where('siswa_id', $siswa->id)->first(),
        ]);
    }

    public function store(Request $request, Tugas $tugas): RedirectResponse
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
            return back()->with('error', 'Tugas ini sudah dinilai guru, jawabannya tidak bisa diubah lagi.');
        }

        if (blank($data['catatan'] ?? null) && ! $request->hasFile('file') && ! $pengumpulan->exists) {
            return back()->withInput()->withErrors(['catatan' => 'Isi catatan jawaban atau unggah file terlebih dahulu.']);
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

        return redirect()->route('siswa.tugas.index')->with('success', $pengumpulan->terlambat
            ? 'Tugas berhasil dikumpulkan, tetapi tercatat melewati batas waktu.'
            : 'Tugas berhasil dikumpulkan.');
    }

    /** Siswa hanya boleh membuka & mengumpulkan tugas untuk kelasnya sendiri. */
    private function pastikanTugasKelasSiswa(Tugas $tugas, Siswa $siswa): void
    {
        abort_unless(in_array((int) $tugas->kelas_id, $siswa->semuaRombelId(), true), 403, 'Tugas ini bukan untuk kelas Anda.');
    }
}
