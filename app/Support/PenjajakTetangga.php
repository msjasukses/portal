<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Menemukan sendiri alamat aplikasi ujian dan aplikasi presensi.
 *
 * Sekolah memasang beberapa aplikasi pada satu domain: portal di `/portal`,
 * ujian di `/cbt`, presensi di `/presensi`. Dulu operator harus menuliskan
 * alamat itu satu per satu ke `.env` portal — dan itu langkah yang paling
 * sering terlewat, karena satu-satunya akibatnya adalah menu yang diam-diam
 * tidak muncul di aplikasi ponsel. Tidak ada galat, tidak ada peringatan,
 * hanya menu yang hilang.
 *
 * Jadi portal mencarinya sendiri, di host yang sama dengan dirinya. Caranya
 * sama persis dengan cara aplikasi ponsel menemukan portal: mengetuk beberapa
 * alamat yang lazim, lalu memastikan yang menjawab memang aplikasi yang
 * dicari — bukan sekadar ada peladen di sana.
 *
 * Yang membuktikan bukan nama aplikasinya (itu bisa diganti tiap sekolah),
 * melainkan keberadaan rute yang khas:
 *
 * - Aplikasi ujian menjawab 401 pada `/api/v1/ujian`, karena rutenya ada dan
 *   menuntut token. Portal menjawab 404 di alamat yang sama.
 * - Presensi menjawab JSON `kunci_salah` pada `barcode_api.php` tanpa kunci.
 *   Jawaban itu sendiri sudah membuktikan berkasnya ada.
 *
 * `.env` tetap menang bila diisi. Sekolah yang menaruh aplikasinya di domain
 * lain, atau yang ingin memastikan tanpa bergantung pada tebakan, cukup
 * mengisi `UJIAN_URL` atau `ABSENSI_URL` seperti dulu.
 *
 * Yang tidak bisa ditemukan sendiri: **kunci API presensi**. Itu rahasia
 * bersama, dan rahasia yang bisa ditebak dari luar bukan rahasia.
 */
class PenjajakTetangga
{
    /** Subfolder yang lazim dipakai, urut dari yang paling sering. */
    private const JALUR_UJIAN = ['cbt', 'ujian', 'exam', 'ujian-msja'];

    private const JALUR_PRESENSI = ['presensi', 'absensi', 'absen'];

    /**
     * Lama hasil penjajakan disimpan.
     *
     * Panjang karena alamat tetangga nyaris tidak pernah berubah, dan arahan
     * dipanggil setiap aplikasi dibuka: tanpa simpanan, 300 ponsel pada jam
     * yang sama berarti ribuan ketukan ke aplikasi sebelah. Kegagalan
     * disimpan juga, lebih singkat, supaya aplikasi yang baru dipasang tidak
     * menunggu sejam untuk ditemukan.
     */
    private const SIMPAN_KETEMU = 21600; // 6 jam

    private const SIMPAN_NIHIL = 900;    // 15 menit

    public function ujian(): ?string
    {
        $dariEnv = trim((string) config('portal.ujian_url'));

        if ($dariEnv !== '') {
            return rtrim($dariEnv, '/');
        }

        return $this->cari('ujian', self::JALUR_UJIAN, fn (string $c) => $this->adalahUjian($c));
    }

    public function presensi(): ?string
    {
        $dariEnv = trim((string) config('services.absensi.url'));

        if ($dariEnv !== '') {
            return rtrim($dariEnv, '/');
        }

        return $this->cari('presensi', self::JALUR_PRESENSI, fn (string $c) => $this->adalahPresensi($c));
    }

    /** Buang hasil simpanan, supaya penjajakan berikutnya mulai dari nol. */
    public function lupakan(): void
    {
        Cache::forget($this->kunciSimpanan('ujian'));
        Cache::forget($this->kunciSimpanan('presensi'));
    }

    // -- Dalaman -------------------------------------------------------------

    /**
     * @param  callable(string): bool  $cocok
     */
    private function cari(string $nama, array $jalur, callable $cocok): ?string
    {
        $kunci = $this->kunciSimpanan($nama);
        $tersimpan = Cache::get($kunci);

        // Nihil disimpan sebagai string kosong, bukan null: null tidak bisa
        // dibedakan dari "belum pernah dijajaki", dan bedanya adalah antara
        // mengetuk sekali tiap 15 menit dan mengetuk pada tiap permintaan.
        if (is_string($tersimpan)) {
            return $tersimpan === '' ? null : $tersimpan;
        }

        $ketemu = null;

        foreach ($this->kandidat($jalur) as $calon) {
            if ($cocok($calon)) {
                $ketemu = $calon;

                break;
            }
        }

        Cache::put(
            $kunci,
            $ketemu ?? '',
            $ketemu !== null ? self::SIMPAN_KETEMU : self::SIMPAN_NIHIL,
        );

        return $ketemu;
    }

    /**
     * Alamat yang pantas dicoba, seluruhnya di host portal sendiri.
     *
     * Tidak pernah keluar dari host ini. Portal yang menebak-nebak alamat di
     * domain lain berarti portal yang bisa dibujuk mengetuk alamat mana pun,
     * dan itu harga yang terlalu mahal untuk sebuah kenyamanan.
     *
     * @return list<string>
     */
    private function kandidat(array $jalur): array
    {
        $pangkal = rtrim(url('/'), '/');

        // Portal kerap berada di subfolder; tetangganya ada di sebelahnya,
        // bukan di dalamnya. Jadi yang dipakai akar domainnya.
        $akar = preg_replace('#^(https?://[^/]+).*$#i', '$1', $pangkal) ?? $pangkal;

        return array_map(fn (string $f) => $akar.'/'.$f, $jalur);
    }

    private function adalahUjian(string $calon): bool
    {
        // 401 berarti rutenya ada dan menuntut token - hanya aplikasi ujian
        // yang punya. Portal sendiri menjawab 404 di alamat yang sama, jadi
        // portal tidak akan pernah menunjuk dirinya sendiri.
        return rescue(
            fn () => Http::timeout(3)->connectTimeout(2)
                ->get($calon.'/api/v1/ujian')->status() === 401,
            false,
            report: false,
        );
    }

    private function adalahPresensi(string $calon): bool
    {
        return rescue(function () use ($calon) {
            $balasan = Http::timeout(3)->connectTimeout(2)
                ->asForm()->acceptJson()
                ->get($calon.'/barcode_api.php', ['aksi' => 'verifikasi']);

            // Kunci sengaja tidak dikirim. Jawaban "kunci salah" atau "belum
            // disetel" sama-sama membuktikan berkasnya ada dan ia memang API
            // barcode - tanpa portal perlu memegang rahasia apa pun untuk
            // sekadar menemukannya.
            return in_array(
                (string) $balasan->json('kode'),
                ['kunci_salah', 'belum_disetel'],
                true,
            );
        }, false, report: false);
    }

    private function kunciSimpanan(string $nama): string
    {
        // Alamat portal ikut jadi kunci: satu basis kode yang melayani
        // beberapa host tidak boleh mewarisi temuan milik host lain.
        return 'tetangga:'.$nama.':'.md5(rtrim(url('/'), '/'));
    }
}
