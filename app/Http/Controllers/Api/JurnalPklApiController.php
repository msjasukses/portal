<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JurnalPklResource;
use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Jurnal PKL/magang siswa (data aplikasi Kurikulum).
 *
 * Aturannya sama dengan menu Jurnal PKL di peramban: hanya bisa diisi pada
 * tanggal yang terjadwal magang di aplikasi Absensi, tidak untuk tanggal yang
 * belum terjadi, dan tidak bisa diubah setelah disetujui pembimbing. Setiap
 * penyimpanan — termasuk perbaikan revisi — kembali berstatus menunggu.
 */
class JurnalPklApiController extends Controller
{
    /** Folder foto kegiatan, sama dengan aplikasi Kurikulum. */
    private const FOLDER = 'uploads/jurnal_pkl';

    public function index(Request $request): JsonResponse
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

        return response()->json([
            'jadwal' => $jadwalList->map(fn (JadwalMagang $jadwal) => [
                'id' => $jadwal->id,
                'tempat' => $jadwal->tempat,
                'pembimbing' => $jadwal->pembimbing,
                'tanggal_mulai' => $jadwal->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $jadwal->tanggal_selesai?->toDateString(),
                'hari' => $jadwal->namaHari(),
                'sedang_berjalan' => $jadwal->sedangBerjalan(),

                // Lembar jurnal siap cetak untuk periode ini. Alamatnya butuh
                // token, jadi aplikasi mengunduhnya lebih dulu — sama seperti
                // lampiran lain — lalu menyerahkannya ke peramban perangkat.
                'cetak_url' => route('api.jurnal.cetak', ['jadwal_id' => $jadwal->id]),
            ])->all(),
            'hari' => $hariMagang->map(fn (array $hari) => [
                'tanggal' => $hari['tanggal']->toDateString(),
                'tempat' => $hari['jadwal']->tempat,
                'jadwal_id' => $hari['jadwal']->id,
                'jurnal' => $hari['jurnal'] ? new JurnalPklResource($hari['jurnal']) : null,
            ])->all(),
            'rekap' => [
                'hari_magang' => $hariMagang->count(),
                'terisi' => $hariMagang->whereNotNull('jurnal')->count(),
                'hari_ini_magang' => $jadwalList->contains(fn (JadwalMagang $jadwal) => $jadwal->berlakuPada(today())),
            ],
        ]);
    }

    public function show(Request $request, JurnalPkl $jurnal): JurnalPklResource
    {
        $this->pastikanMilikSiswa($request, $jurnal);

        return new JurnalPklResource($jurnal);
    }

    public function store(Request $request): JurnalPklResource
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

        return (new JurnalPklResource($item))->additional([
            'pesan' => 'Jurnal PKL tanggal '.$tanggal->translatedFormat('d F Y').' berhasil disimpan.',
        ]);
    }

    public function destroy(Request $request, JurnalPkl $jurnal): JsonResponse
    {
        $this->pastikanMilikSiswa($request, $jurnal);
        abort_unless($jurnal->bisaDiubahSiswa(), 403, 'Jurnal yang sudah disetujui tidak bisa dihapus.');

        if ($jurnal->foto) {
            Storage::disk('kurikulum')->delete($jurnal->foto);
        }

        $jurnal->delete();

        return response()->json(['pesan' => 'Jurnal PKL berhasil dihapus.']);
    }

    private function siswa(Request $request): Siswa
    {
        return $request->user('siswa');
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
