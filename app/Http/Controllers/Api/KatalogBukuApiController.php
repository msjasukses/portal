<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BukuResource;
use App\Models\Kurikulum\BukuDigital;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Katalog buku digital (data aplikasi Kurikulum).
 *
 * Isinya cermin menu Katalog Buku di peramban, dengan hak yang sama: siswa
 * boleh menelusuri, melihat rincian, dan membaca — tidak mengunduh dari SIBI
 * dan tidak menyinkron katalog. Kedua aksi itu memanggil server luar dan
 * menulis ke penyimpanan sekolah, jadi tetap milik guru dan admin.
 */
class KatalogBukuApiController extends Controller
{
    /** Penyaring katalog: nama query → kolom tabel. Sama dengan di peramban. */
    private const PENYARING = [
        'jenjang' => 'jenjang',
        'kategori' => 'kategori',
        'jenis_buku' => 'jenis_buku',
        'kurikulum' => 'kurikulum',
        'tipe' => 'tipe',
        'mata_pelajaran' => 'mata_pelajaran',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BukuDigital::query()->cari($request->query('q'));

        foreach (self::PENYARING as $namaQuery => $kolom) {
            $query->when(
                filled($request->query($namaQuery)),
                fn ($q) => $q->where($kolom, $request->query($namaQuery)),
            );
        }

        // Penyaring khusus: hanya buku yang berkasnya sudah ada di sekolah.
        $query->when($request->query('unduhan') === 'lokal', fn ($q) => $q->whereNotNull('file_path'))
            ->when($request->query('unduhan') === 'belum', fn ($q) => $q->whereNull('file_path'));

        $buku = $query
            // Buku yang berkasnya sudah di sekolah tampil dulu: itu yang bisa
            // dibuka siswa tanpa bergantung pada internet maupun server SIBI.
            ->orderByRaw('file_path IS NULL')
            ->orderBy('judul')
            ->paginate(12)
            ->withQueryString();

        return BukuResource::collection($buku)->additional([
            'pilihan' => $this->pilihanPenyaring(),
            'ringkasan' => [
                'total' => BukuDigital::count(),
                'lokal' => BukuDigital::whereNotNull('file_path')->count(),
            ],
        ]);
    }

    public function show(BukuDigital $buku): BukuResource
    {
        $buku->load('bab.subBab');

        return (new BukuResource($buku))->additional([
            // Buku serupa: mapel yang sama atau jenjang yang sama. Kedua syarat
            // dikurung agar tidak membatalkan pengecualian buku ini sendiri.
            'serupa' => BukuResource::collection(
                BukuDigital::where('id', '!=', $buku->id)
                    ->where(function ($q) use ($buku) {
                        $q->when($buku->mata_pelajaran, fn ($w, $v) => $w->orWhere('mata_pelajaran', $v))
                            ->when($buku->jenjang, fn ($w, $v) => $w->orWhere('jenjang', $v));
                    })
                    ->orderByRaw('file_path IS NULL')
                    ->limit(6)
                    ->get(),
            ),
        ]);
    }

    /**
     * Nilai yang benar-benar ada di katalog untuk tiap penyaring, supaya
     * pilihan di aplikasi selalu mengikuti isi katalog sekolah — bukan daftar
     * tetap yang ikut terbangun di dalam APK.
     *
     * @return array<string, list<string>>
     */
    private function pilihanPenyaring(): array
    {
        $pilihan = [];

        foreach (self::PENYARING as $namaQuery => $kolom) {
            $pilihan[$namaQuery] = BukuDigital::query()
                ->whereNotNull($kolom)
                ->where($kolom, '!=', '')
                ->distinct()
                ->orderBy($kolom)
                ->pluck($kolom)
                ->all();
        }

        return $pilihan;
    }
}
