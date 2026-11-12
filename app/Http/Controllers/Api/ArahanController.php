<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AbsensiBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peta modul yang dipajang aplikasi ponsel.
 *
 * Gunanya satu: operator cukup mengetik alamat portal — hanya IP atau domain,
 * tanpa subfolder — lalu aplikasi menemukan sendiri menu apa saja yang hidup
 * dan di mana aplikasi ujian berada. Menambah atau mematikan satu menu jadi
 * urusan server, bukan alasan membangun ulang APK.
 *
 * Endpoint ini dipanggil sebelum siswa masuk, jadi ia tidak boleh pernah
 * gagal: nama sekolah yang perlu menengok database aplikasi lain dibungkus
 * rescue(), dan kalau database itu mati namanya sekadar kosong.
 */
class ArahanController extends Controller
{
    public function __construct(private AbsensiBarcode $barcode) {}

    public function __invoke(): JsonResponse
    {
        $ujian = trim((string) config('portal.ujian_url'));

        return response()->json([
            'ok' => true,
            'aplikasi' => 'Portal Siswa',
            'versi_api' => 1,
            'sekolah' => $this->namaSekolah(),
            'portal_url' => rtrim(url('/'), '/'),

            // Alamat aplikasi ujian, lengkap dengan subfoldernya. Aturan
            // ujiannya tetap milik aplikasi ujian; portal hanya menunjukkan
            // jalan. Kosong = menu ujian disembunyikan aplikasi.
            'ujian_url' => $ujian !== '' ? rtrim($ujian, '/') : null,

            'menu' => $this->menu(),
        ]);
    }

    /**
     * Menu yang hidup, urut seperti sidebar portal di peramban.
     *
     * @return list<array{kunci: string, label: string, ikon: string, jalur: string}>
     */
    private function menu(): array
    {
        $menu = [
            ['kunci' => 'dashboard', 'label' => 'Ringkasan', 'ikon' => 'grid', 'jalur' => '/api/v1/dashboard'],
        ];

        // Absen barcode ikut mati bila alamat atau kunci aplikasi Absensi belum
        // diisi — aturan yang sama dengan menu Absen Barcode di peramban.
        if ($this->barcode->aktif()) {
            $menu[] = ['kunci' => 'absen', 'label' => 'Absen Barcode', 'ikon' => 'key', 'jalur' => '/api/v1/absen'];
        }

        return [
            ...$menu,
            ['kunci' => 'absensi', 'label' => 'Absensi Bulanan', 'ikon' => 'calendar', 'jalur' => '/api/v1/absensi'],
            ['kunci' => 'agenda', 'label' => 'Agenda Mengajar', 'ikon' => 'clock', 'jalur' => '/api/v1/agenda'],
            ['kunci' => 'tugas', 'label' => 'Tugas', 'ikon' => 'file-text', 'jalur' => '/api/v1/tugas'],
            ['kunci' => 'materi', 'label' => 'Materi Online', 'ikon' => 'book', 'jalur' => '/api/v1/materi'],
            ...$this->katalogBuku(),
            ['kunci' => 'jurnal-pkl', 'label' => 'Jurnal PKL', 'ikon' => 'briefcase', 'jalur' => '/api/v1/jurnal-pkl'],
            ['kunci' => 'prestasi', 'label' => 'Prestasi', 'ikon' => 'award', 'jalur' => '/api/v1/prestasi'],
            ['kunci' => 'pelanggaran', 'label' => 'Pelanggaran', 'ikon' => 'shield', 'jalur' => '/api/v1/pelanggaran'],
        ];
    }

    /**
     * Menu katalog buku, bila aplikasi Kurikulum di sekolah ini memang punya
     * katalognya. Katalog buku digital baru ada pada Kurikulum terbaru, jadi
     * sekolah yang belum memperbaruinya tidak boleh dipajangi menu yang pasti
     * gagal dibuka. Seperti seluruh arahan, kegagalan database pun tidak boleh
     * menjatuhkan endpoint ini — jadi jawabannya "tidak ada".
     *
     * @return list<array{kunci: string, label: string, ikon: string, jalur: string}>
     */
    private function katalogBuku(): array
    {
        $ada = rescue(
            fn () => Schema::connection('kurikulum')->hasTable('buku_digital'),
            false,
            report: false,
        );

        return $ada
            ? [['kunci' => 'katalog-buku', 'label' => 'Katalog Buku', 'ikon' => 'library', 'jalur' => '/api/v1/katalog-buku']]
            : [];
    }

    private function namaSekolah(): string
    {
        $dariKurikulum = rescue(
            fn () => DB::connection('kurikulum')->table('identitas_sekolah')->value('nama_sekolah'),
            null,
            report: false,
        );

        return (string) ($dariKurikulum ?: setting('site_name', config('app.name')));
    }
}
