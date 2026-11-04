<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Jurnal PKL / magang siswa (data aplikasi Kurikulum). Sama dengan menu Jurnal
 * PKL siswa di Kurikulum: jurnal hanya bisa diisi pada tanggal yang terjadwal
 * magang di aplikasi Absensi, dan tidak bisa diubah setelah disetujui
 * pembimbing. Foto disimpan di storage Kurikulum agar terlihat oleh pembimbing.
 */
class JurnalPklController extends Controller
{
    /** Folder foto kegiatan, sama dengan aplikasi Kurikulum. */
    private const FOLDER = 'uploads/jurnal_pkl';

    public function index(Request $request): View
    {
        $siswa = $this->siswa($request);
        $jadwalList = JadwalMagang::milik($siswa)->orderByDesc('tanggal_mulai')->get();

        $jurnal = JurnalPkl::where('siswa_id', $siswa->id)->get()
            ->keyBy(fn (JurnalPkl $item) => $item->tanggal->toDateString());

        // Hari magang dari semua jadwal sampai hari ini, terbaru dulu.
        $hariMagang = $jadwalList
            ->flatMap(fn (JadwalMagang $jadwal) => collect($jadwal->tanggalMagang())->map(fn (Carbon $tanggal) => [
                'tanggal' => $tanggal,
                'jadwal' => $jadwal,
                'jurnal' => $jurnal->get($tanggal->toDateString()),
            ]))
            ->unique(fn (array $hari) => $hari['tanggal']->toDateString())
            ->sortByDesc(fn (array $hari) => $hari['tanggal']->timestamp)
            ->values();

        return view('siswa.jurnal.index', [
            'jadwalList' => $jadwalList,
            'hariMagang' => $hariMagang,
            'hariIniMagang' => $jadwalList->first(fn (JadwalMagang $jadwal) => $jadwal->berlakuPada(today())),
            'jumlahTerisi' => $hariMagang->whereNotNull('jurnal')->count(),
        ]);
    }

    /** Form isi / ubah jurnal pada satu tanggal magang. */
    public function form(Request $request): View|RedirectResponse
    {
        $siswa = $this->siswa($request);
        $tanggal = $this->tanggalDariRequest($request);
        $jadwal = $this->jadwalPada($siswa, $tanggal);

        if (! $jadwal) {
            return redirect()->route('siswa.jurnal.index')
                ->with('error', 'Tanggal '.$tanggal->translatedFormat('d F Y').' bukan hari magang Anda.');
        }

        $item = JurnalPkl::where('siswa_id', $siswa->id)->whereDate('tanggal', $tanggal)->first();

        if ($item && ! $item->bisaDiubahSiswa()) {
            return redirect()->route('siswa.jurnal.show', $item)->with('error', 'Jurnal yang sudah disetujui tidak bisa diubah.');
        }

        return view('siswa.jurnal.form', compact('tanggal', 'jadwal', 'item'));
    }

    public function store(Request $request): RedirectResponse
    {
        $siswa = $this->siswa($request);
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
            'kegiatan' => ['required', 'string', 'max:5000'],
            'hasil' => ['nullable', 'string', 'max:5000'],
            'kendala' => ['nullable', 'string', 'max:5000'],
            'foto' => ['nullable', 'image', 'max:5120'],
        ], [
            'tanggal.before_or_equal' => 'Jurnal tidak bisa diisi untuk tanggal yang belum terjadi.',
        ], [
            'jam_mulai' => 'Jam Mulai',
            'jam_selesai' => 'Jam Selesai',
            'kegiatan' => 'Kegiatan',
            'hasil' => 'Hasil / Kompetensi',
            'kendala' => 'Kendala',
            'foto' => 'Foto Kegiatan',
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $jadwal = $this->jadwalPada($siswa, $tanggal);
        abort_unless($jadwal, 422, 'Tanggal tersebut bukan hari magang Anda.');

        $item = JurnalPkl::where('siswa_id', $siswa->id)->whereDate('tanggal', $tanggal)->first();
        abort_if($item && ! $item->bisaDiubahSiswa(), 403, 'Jurnal yang sudah disetujui tidak bisa diubah.');

        unset($data['foto']);
        if ($request->hasFile('foto')) {
            if ($item?->foto) {
                Storage::disk('kurikulum')->delete($item->foto);
            }
            $data['foto'] = $request->file('foto')->store(self::FOLDER, 'kurikulum');
        }

        $item ??= new JurnalPkl(['siswa_id' => $siswa->id]);
        $item->fill([
            ...$data,
            'jadwal_magang_id' => $jadwal->id,
            'tempat' => $jadwal->tempat,
            'tanggal' => $tanggal->toDateString(),
            // Setiap simpan (termasuk perbaikan revisi) kembali menunggu pemeriksaan.
            'status' => JurnalPkl::STATUS_MENUNGGU,
        ]);
        $item->save();

        return redirect()->route('siswa.jurnal.index')
            ->with('success', 'Jurnal PKL tanggal '.$tanggal->translatedFormat('d F Y').' berhasil disimpan.');
    }

    public function show(Request $request, JurnalPkl $jurnal): View
    {
        $this->pastikanMilikSiswa($request, $jurnal);

        return view('siswa.jurnal.show', [
            'item' => $jurnal,
            'jadwal' => rescue(fn () => JadwalMagang::find($jurnal->jadwal_magang_id), null, false),
            'pemeriksa' => $jurnal->diperiksa_oleh ? rescue(fn () => $jurnal->pemeriksa?->name, null, false) : null,
        ]);
    }

    public function destroy(Request $request, JurnalPkl $jurnal): RedirectResponse
    {
        $this->pastikanMilikSiswa($request, $jurnal);
        abort_unless($jurnal->bisaDiubahSiswa(), 403, 'Jurnal yang sudah disetujui tidak bisa dihapus.');

        if ($jurnal->foto) {
            Storage::disk('kurikulum')->delete($jurnal->foto);
        }

        $jurnal->delete();

        return redirect()->route('siswa.jurnal.index')->with('success', 'Jurnal PKL berhasil dihapus.');
    }

    /** Cetak jurnal pada satu jadwal magang (default: jadwal terbaru). */
    public function cetak(Request $request): View
    {
        $siswa = $this->siswa($request);

        $jadwal = JadwalMagang::milik($siswa)
            ->when($request->query('jadwal_id'), fn ($query, $id) => $query->whereKey($id))
            ->orderByDesc('tanggal_mulai')
            ->firstOrFail();

        return view('siswa.jurnal.cetak', [
            'siswa' => $siswa,
            'kelas' => $siswa->rombelAktif()?->nama_rombel,
            'jadwal' => $jadwal,
            'items' => JurnalPkl::where('siswa_id', $siswa->id)
                ->where('jadwal_magang_id', $jadwal->id)
                ->orderBy('tanggal')
                ->get(),
            'namaSekolah' => rescue(fn () => DB::connection('kurikulum')->table('identitas_sekolah')->value('nama_sekolah'), null, false)
                ?: setting('site_name', config('app.name')),
        ]);
    }

    private function siswa(Request $request): Siswa
    {
        return $request->user('siswa');
    }

    private function tanggalDariRequest(Request $request): Carbon
    {
        $tanggal = rescue(fn () => Carbon::parse($request->query('tanggal', today()->toDateString())), today(), false)->startOfDay();

        // Jurnal tidak boleh diisi untuk hari yang belum terjadi.
        return $tanggal->gt(today()) ? today() : $tanggal;
    }

    /** Jadwal magang siswa yang berlaku pada tanggal tersebut (periode + hari), atau null. */
    private function jadwalPada(Siswa $siswa, Carbon $tanggal): ?JadwalMagang
    {
        return JadwalMagang::milik($siswa)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->get()
            ->first(fn (JadwalMagang $jadwal) => $jadwal->berlakuPada($tanggal));
    }

    /** Jurnal siswa lain diperlakukan seolah tidak ada. */
    private function pastikanMilikSiswa(Request $request, JurnalPkl $jurnal): void
    {
        abort_unless((int) $jurnal->siswa_id === (int) $request->user('siswa')->id, 404);
    }
}
