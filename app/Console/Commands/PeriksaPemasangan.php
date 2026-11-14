<?php

namespace App\Console\Commands;

use App\Support\LogoSekolah;
use App\Support\RilisAplikasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Memeriksa sebuah pemasangan portal dan mengatakan apa yang belum beres.
 *
 * Portal ini dipasang di tempat yang berbeda-beda: laptop guru lewat Laragon,
 * VPS Virtualmin dengan `public_html/portal`, VPS Hestia dengan `web/portal`.
 * Yang berbeda bukan hanya jalurnya — database aplikasi tetangga bisa belum
 * diberi izin, folder storage-nya bisa tidak ikut dipindah, dan alamat
 * aplikasi ujian bisa belum diisi.
 *
 * Semua itu gagal dengan sopan ketika portal berjalan: menu yang mati sekadar
 * hilang, logo yang tidak ada diganti logo bawaan, rilis yang belum diunggah
 * sekadar tidak ditawarkan. Sifat itu disengaja — tetapi akibatnya pemasangan
 * yang setengah jadi terlihat persis seperti pemasangan yang sehat, dan baru
 * ketahuan ketika seorang siswa mengeluh. Perintah ini yang membedakannya.
 *
 *     php artisan portal:periksa
 */
class PeriksaPemasangan extends Command
{
    protected $signature = 'portal:periksa';

    protected $description = 'Periksa kesehatan pemasangan portal ini: database, storage, rilis, logo.';

    /** @var list<array{taraf: string, judul: string, pesan: string}> */
    private array $hasil = [];

    public function handle(RilisAplikasi $rilis, LogoSekolah $logo): int
    {
        $this->newLine();
        $this->line('  <options=bold>Pemeriksaan pemasangan portal</>');
        $this->line('  '.str_repeat('-', 64));

        $this->periksaLingkungan();
        $this->periksaDatabase();
        $this->periksaDisk();
        $this->periksaRilis($rilis);
        $this->periksaLogo($logo);
        $this->periksaUjian();

        return $this->ringkas();
    }

    // -- Pemeriksaan ---------------------------------------------------------

    private function periksaLingkungan(): void
    {
        $this->baik('Jalur aplikasi', base_path());
        $this->baik('APP_URL', (string) config('app.url'));

        // Jejak tumpukan pada server produksi memajang jalur absolut, versi
        // pustaka, dan pada galat tertentu isi .env. Itu peta bagi siapa pun
        // yang iseng mengetik alamat yang salah.
        if (config('app.debug') && config('app.env') === 'production') {
            $this->catatan(
                'APP_DEBUG',
                'menyala di lingkungan production; setel APP_DEBUG=false lalu config:clear',
            );
        } else {
            $this->baik(
                'APP_DEBUG',
                config('app.debug') ? 'menyala (lingkungan '.config('app.env').')' : 'mati',
            );
        }

        $this->periksaTulis('Folder storage/logs', storage_path('logs'));
        $this->periksaTulis('Folder bootstrap/cache', base_path('bootstrap/cache'));
    }

    private function periksaDatabase(): void
    {
        // Portal tidak bisa berbuat apa-apa tanpa dua yang pertama: satu
        // menyimpan tokennya sendiri, satu memegang data siswa. Sisanya hanya
        // mengisi menu, dan menu yang mati memang boleh sekadar hilang.
        // Koneksi portal dibaca dari database.default, bukan dipaku "mysql":
        // pemasangan boleh memakai mariadb, dan lingkungan uji memakai sqlite.
        // Perintah yang memaku namanya akan melapor gagal di tempat yang
        // sebenarnya sehat - persis jenis kesalahan yang hendak dicegahnya.
        $wajib = [
            (string) config('database.default') => 'Database portal',
            'datacenter' => 'Database Data Center',
        ];

        $pelengkap = [
            'absensi' => 'Database Absensi',
            'kurikulum' => 'Database Kurikulum',
            'kesiswaan' => 'Database Kesiswaan',
        ];

        foreach ($wajib + $pelengkap as $nama => $judul) {
            $galat = $this->cobaSambung($nama);

            if ($galat === null) {
                $this->baik($judul, (string) config("database.connections.$nama.database"));
            } elseif (isset($wajib[$nama])) {
                $this->gagal($judul, $galat);
            } else {
                $this->catatan($judul.' (menunya hilang)', $galat);
            }
        }
    }

    private function periksaDisk(): void
    {
        $disk = [
            'kurikulum' => 'Storage Kurikulum (materi, tugas)',
            'datacenter' => 'Storage Data Center (logo sekolah)',
        ];

        foreach ($disk as $nama => $judul) {
            $akar = (string) config("filesystems.disks.$nama.root");

            if ($akar === '') {
                $this->gagal($judul, "disk \"$nama\" tidak ada di config/filesystems.php");

                continue;
            }

            // Jalurnya ikut dicetak apa pun hasilnya. Inilah satu-satunya
            // tempat seseorang bisa melihat ke mana portal sebenarnya
            // menunjuk setelah pemasangannya dipindahkan.
            is_dir($akar)
                ? $this->baik($judul, $akar)
                : $this->catatan($judul, "folder tidak ada: $akar");
        }
    }

    private function periksaRilis(RilisAplikasi $rilis): void
    {
        $berkas = (string) config('portal.rilis_manifes');
        $manifes = $rilis->manifes();

        if ($manifes !== null) {
            $this->baik('Rilis APK', sprintf(
                'versi %s, kode versi %d, %d berkas',
                $manifes['versi'],
                $manifes['kode_versi'],
                count($manifes['apk']),
            ));

            return;
        }

        is_file($berkas)
            ? $this->catatan('Rilis APK', "manifes ada tetapi tidak layak dipakai: $berkas")
            : $this->catatan('Rilis APK', "belum ada rilis yang diunggah ($berkas)");
    }

    private function periksaLogo(LogoSekolah $logo): void
    {
        $jalur = $logo->jalur();

        $jalur !== null
            ? $this->baik('Logo sekolah', $jalur)
            : $this->catatan('Logo sekolah', 'belum ada; unggah di Data Center, Pengaturan Aplikasi');
    }

    private function periksaUjian(): void
    {
        $ujian = trim((string) config('portal.ujian_url'));

        $ujian !== ''
            ? $this->baik('Alamat aplikasi ujian', $ujian)
            : $this->catatan('Alamat aplikasi ujian', 'UJIAN_URL kosong; menu Ujian/CBT disembunyikan');
    }

    // -- Alat ----------------------------------------------------------------

    private function cobaSambung(string $koneksi): ?string
    {
        try {
            DB::connection($koneksi)->getPdo();

            return null;
        } catch (Throwable $e) {
            // Pesan PDO panjang dan berulang; baris pertamanya yang berguna.
            return trim(explode("\n", $e->getMessage())[0]);
        }
    }

    private function periksaTulis(string $judul, string $folder): void
    {
        is_writable($folder)
            ? $this->baik($judul, 'bisa ditulis')
            : $this->gagal($judul, "tidak bisa ditulis: $folder");
    }

    private function baik(string $judul, string $pesan): void
    {
        $this->catat('baik', $judul, $pesan);
    }

    private function catatan(string $judul, string $pesan): void
    {
        $this->catat('catatan', $judul, $pesan);
    }

    private function gagal(string $judul, string $pesan): void
    {
        $this->catat('gagal', $judul, $pesan);
    }

    private function catat(string $taraf, string $judul, string $pesan): void
    {
        $this->hasil[] = ['taraf' => $taraf, 'judul' => $judul, 'pesan' => $pesan];

        [$tanda, $warna] = match ($taraf) {
            'baik' => ['OK   ', 'green'],
            'catatan' => ['CATAT', 'yellow'],
            default => ['GAGAL', 'red'],
        };

        // Komponen bawaan Laravel, bukan sprintf dengan padding sendiri:
        // gaya keluaran Laravel memampatkan spasi beruntun, sehingga kolom
        // yang diratakan dengan tangan runtuh justru di terminal sungguhan.
        $this->components->twoColumnDetail(
            sprintf('<fg=%s>%s</> %s', $warna, $tanda, $judul),
            $pesan,
        );
    }

    private function ringkas(): int
    {
        $hitung = fn (string $taraf) => count(array_filter(
            $this->hasil,
            fn (array $h) => $h['taraf'] === $taraf,
        ));

        $gagal = $hitung('gagal');
        $catatan = $hitung('catatan');

        $this->line('  '.str_repeat('-', 64));

        if ($gagal > 0) {
            $this->line("  <fg=red;options=bold>$gagal hal menghentikan portal ini.</> $catatan hal lain perlu diperhatikan.");
            $this->newLine();

            return self::FAILURE;
        }

        $catatan > 0
            ? $this->line("  <fg=yellow;options=bold>Portal berjalan.</> $catatan hal berjalan dengan keterbatasan.")
            : $this->line('  <fg=green;options=bold>Semuanya beres.</>');

        $this->newLine();

        return self::SUCCESS;
    }
}
