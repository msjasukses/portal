<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Manifes rilis APK yang dipajang portal kepada aplikasi ponsel.
 *
 * Catatannya tinggal di `public/rilis/terkini.json`, bersebelahan dengan
 * berkas APK-nya sendiri. Sebuah rilis adalah peristiwa mengirim berkas, jadi
 * catatannya sebaiknya bepergian bersama berkas itu: mengunggah APK lalu lupa
 * memperbarui satu baris di `.env` di tempat lain adalah kesalahan yang
 * menunggu terjadi, dan kegagalannya diam.
 *
 * Seluruh kelas ini dibangun di atas satu syarat: `GET /api/v1/arahan`
 * dipanggil sebelum siswa masuk dan tidak boleh pernah gagal. Berkas yang
 * tidak ada, rusak, tidak terbaca, atau berisi hal yang tidak dikenali
 * berarti "tidak ada pembaruan" — bukan galat, bukan 500.
 */
class RilisAplikasi
{
    /**
     * Manifes yang siap disisipkan ke balasan arahan, atau null.
     *
     * @return array<string, mixed>|null
     */
    public function manifes(): ?array
    {
        $berkas = (string) config('portal.rilis_manifes');

        if (! is_file($berkas) || ! is_readable($berkas)) {
            return null;
        }

        $isi = rescue(
            fn () => json_decode($this->tanpaBom((string) file_get_contents($berkas)), true, flags: JSON_THROW_ON_ERROR),
            null,
            report: false,
        );

        return is_array($isi) ? $this->bersihkan($isi) : null;
    }

    /**
     * Buang BOM UTF-8 di awal berkas.
     *
     * Notepad dan PowerShell di Windows menambahkannya tanpa diminta, dan
     * json_decode menolak berkas yang dimulai dengan tiga bita itu. Yang
     * membuatnya pantas dimaafkan di sini adalah bentuk kegagalannya: manifes
     * dianggap tidak ada, portal berkata "tidak ada pembaruan", dan tidak
     * seorang siswa pun menerima rilisnya — tanpa satu pesan galat di mana
     * pun. Operator yang menyunting manifes di server tidak akan pernah
     * menduga tiga bita tak terlihat sebagai sebabnya.
     */
    private function tanpaBom(string $isi): string
    {
        // Disusun dari nilai bita, bukan dari escape di dalam tanda kutip:
        // pembenah gaya kode mengubah escape semacam itu menjadi BOM
        // literal yang tak terlihat di dalam berkas sumber, dan hal tak
        // terlihat adalah hal yang akan terhapus seseorang tanpa menyadarinya.
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);

        return str_starts_with($isi, $bom) ? substr($isi, 3) : $isi;
    }

    /**
     * Manifes yang bentuknya sudah dipastikan, atau null bila tidak layak
     * dikirim.
     *
     * Manifes separuh jadi lebih buruk daripada tidak ada manifes: aplikasi
     * akan memajang tombol Perbarui yang menuju ke tempat yang tidak ada, dan
     * siswa membacanya sebagai aplikasi yang rusak. Jadi yang tidak lengkap
     * dibuang di sini, selagi masih di server.
     *
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>|null
     */
    private function bersihkan(array $isi): ?array
    {
        $kode = filter_var($isi['kode_versi'] ?? null, FILTER_VALIDATE_INT);

        if ($kode === false || $kode < 1) {
            return null;
        }

        $apk = $this->berkasApk($isi['apk'] ?? null);

        if ($apk === []) {
            return null;
        }

        return [
            'kode_versi' => $kode,
            'versi' => trim((string) ($isi['versi'] ?? $kode)),

            // Tanpa batas wajib yang ditulis sendiri, tidak ada pembaruan yang
            // memaksa. Lebih baik begitu: memaksa seluruh sekolah memperbarui
            // adalah keputusan yang harus diambil sengaja, bukan diwarisi dari
            // nilai bawaan.
            'wajib_minimal' => max(0, (int) ($isi['wajib_minimal'] ?? 0)),

            'terbit_pada' => $this->tanggal($isi['terbit_pada'] ?? null),
            'catatan' => trim((string) ($isi['catatan'] ?? '')) ?: null,
            'apk' => $apk,
        ];
    }

    /**
     * Berkas APK per arsitektur, hanya yang lengkap.
     *
     * @return array<string, array{url: string, ukuran: int, sha256: string}>
     */
    private function berkasApk(mixed $mentah): array
    {
        if (! is_array($mentah)) {
            return [];
        }

        $bersih = [];

        foreach ($mentah as $abi => $berkas) {
            if (! is_string($abi) || ! is_array($berkas)) {
                continue;
            }

            $url = trim((string) ($berkas['url'] ?? ''));
            $ukuran = filter_var($berkas['ukuran'] ?? null, FILTER_VALIDATE_INT);
            $sha = strtolower(trim((string) ($berkas['sha256'] ?? '')));

            // SHA-256 heksadesimal selalu 64 huruf. Yang lebih pendek hampir
            // selalu berarti sidik jari yang terpotong saat ditempel, dan
            // aplikasi akan menolak setiap unduhan tanpa pernah menjelaskan
            // kenapa — jadi lebih baik manifesnya yang menolaknya di sini.
            if ($url === '' || $ukuran === false || $ukuran < 1 || ! preg_match('/^[0-9a-f]{64}$/', $sha)) {
                continue;
            }

            $bersih[$abi] = [
                'url' => $this->alamatPenuh($url),
                'ukuran' => $ukuran,
                'sha256' => $sha,
            ];
        }

        return $bersih;
    }

    /**
     * Alamat yang ditulis relatif dilengkapi dengan alamat portal ini.
     *
     * Inilah yang membuat satu `terkini.json` yang sama bisa dipakai di
     * jaringan sekolah dan di VPS tanpa diedit: yang ditulis operator cukup
     * `rilis/cbt-siswa-1.2.0-arm64-v8a.apk`, dan domainnya diisi server yang
     * sedang menjawab. Alamat yang sudah lengkap dihormati apa adanya —
     * sekolah yang menaruh APK-nya di tempat lain tetap bisa.
     */
    private function alamatPenuh(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : url(ltrim($url, '/'));
    }

    /**
     * Tanggal terbit dalam bentuk ISO, atau null bila tidak terbaca.
     */
    private function tanggal(mixed $mentah): ?string
    {
        $teks = trim((string) ($mentah ?? ''));

        if ($teks === '') {
            return null;
        }

        return rescue(
            fn () => Carbon::parse($teks)->toDateString(),
            null,
            report: false,
        );
    }
}
