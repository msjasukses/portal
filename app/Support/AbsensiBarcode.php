<?php

namespace App\Support;

use App\Models\Datacenter\Siswa;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jembatan ke endpoint barcode aplikasi Absensi (barcode_api.php).
 *
 * Layar Tap aplikasi Absensi memajang QR yang tokennya berganti tiap 2 menit.
 * Siswa memindainya, Portal Siswa yang sudah tahu siapa yang login mengirimkan
 * token + nomor induknya ke sana, lalu aplikasi Absensi yang menuliskan
 * absensinya dengan aturan yang sama persis dengan kartu RFID dan mesin sidik
 * jari. Jadi portal tidak pernah menulis ke tabel absensi sendiri.
 *
 * Kunci API hanya dipakai dari sisi server; token hasil pindaian yang datang
 * dari HP siswa tidak pernah dipercaya tanpa diperiksa aplikasi Absensi.
 */
class AbsensiBarcode
{
    public function __construct(private PenjajakTetangga $tetangga) {}

    /** Token barcode aplikasi Absensi: barcode_token.token = 32 digit heksadesimal. */
    private const POLA_TOKEN = '/^[0-9a-f]{32}$/i';

    /**
     * Menu Absen Barcode hidup selama kunci aplikasi Absensi terisi.
     *
     * Alamatnya sengaja tidak ikut menentukan. `ABSENSI_URL` boleh kosong —
     * portal mencarinya sendiri — dan pencarian itu bisa meleset: aplikasi
     * Absensi di domain lain, di porta lain, atau sekadar sedang tidak
     * menjawab saat dijajaki. Mematikan menunya karena itu berarti siswa
     * kehilangan satu-satunya cara absen padahal aplikasinya ada. Lebih baik
     * menunya tetap ada dan kegagalannya dikatakan saat memindai, lengkap
     * dengan sebabnya, daripada menu yang diam-diam menghilang.
     *
     * Kuncinya tetap wajib: ia rahasia bersama antara dua aplikasi, tidak
     * pernah bisa ditebak portal, dan tanpa itu setiap pindaian pasti ditolak.
     */
    public function aktif(): bool
    {
        return filled(config('services.absensi.barcode_key'));
    }

    /** Alamat aplikasi Absensi: dari `.env` bila diisi, atau hasil penjajakan. */
    public function alamat(): ?string
    {
        return $this->tetangga->presensi();
    }

    /**
     * Token dari hasil pindaian. Isi QR bisa berupa alamat portal dengan token
     * di query (URL_PORTAL_ABSENSI terisi) atau token mentah (belum disetel),
     * jadi keduanya diterima. null = bukan token barcode absensi.
     */
    public static function ambilToken(string $hasilPindai): ?string
    {
        $hasilPindai = trim($hasilPindai);
        $query = parse_url($hasilPindai, PHP_URL_QUERY);

        if (is_string($query)) {
            parse_str($query, $bagian);
            $hasilPindai = trim((string) ($bagian['token'] ?? ''));
        }

        return preg_match(self::POLA_TOKEN, $hasilPindai) === 1 ? strtolower($hasilPindai) : null;
    }

    /**
     * Periksa token tanpa menulis apa pun, supaya siswa tahu barcode sudah
     * berganti sebelum menekan tombol absen.
     *
     * @return array{ok: bool, kode?: string, pesan?: string, sisa_detik?: int, berlaku_sampai?: string}
     */
    public function verifikasi(string $token): array
    {
        return $this->panggil(false, ['aksi' => 'verifikasi', 'token' => $token]);
    }

    /**
     * Catat absensi siswa yang sedang login untuk token hasil pindaian.
     *
     * jenis "ulang" berarti siswa baru saja absen (masih dalam jeda aplikasi
     * Absensi) sehingga tidak ditulis ulang — bukan kegagalan.
     *
     * @return array{ok: bool, kode?: string, pesan?: string, jenis?: string, jam?: string, jam_masuk?: ?string, terlambat?: bool, nama?: string, kelas?: ?string}
     */
    public function absen(Siswa $siswa, string $token): array
    {
        return $this->panggil(true, [
            'aksi' => 'absen',
            'token' => $token,
            'tipe' => 'siswa',
            'nomor_induk' => $siswa->kunciAbsensi(),
        ]);
    }

    /**
     * Gangguan jaringan dibedakan dari penolakan aplikasi Absensi: yang pertama
     * boleh dicoba lagi, yang kedua perlu pindai ulang.
     *
     * @param  array<string, string>  $muatan
     * @return array<string, mixed>
     */
    private function panggil(bool $tulis, array $muatan): array
    {
        if (! $this->aktif()) {
            return $this->gagal('belum_disetel', 'Absen barcode belum disetel di portal. Hubungi admin sekolah.');
        }

        // Alamat baru dicari di sini, bukan saat menu digambar: penjajakan yang
        // meleset tidak boleh menghilangkan menunya, hanya menggagalkan pindaian
        // ini — dan siswa pantas tahu bedanya dari kegagalan jaringan biasa.
        $pangkal = $this->alamat();

        if (blank($pangkal)) {
            return $this->gagal('alamat_nihil', 'Alamat aplikasi Absensi belum ditemukan. Minta admin sekolah mengisi ABSENSI_URL di pengaturan portal.');
        }

        $muatan['kunci'] = (string) config('services.absensi.barcode_key');
        $alamat = rtrim($pangkal, '/').'/barcode_api.php';

        try {
            $permintaan = $this->permintaan();
            $respons = $tulis ? $permintaan->post($alamat, $muatan) : $permintaan->get($alamat, $muatan);
        } catch (Throwable $e) {
            Log::warning('Absen barcode: aplikasi Absensi tidak bisa dihubungi. '.$e->getMessage());

            return $this->gagal('koneksi', 'Aplikasi Absensi tidak bisa dihubungi. Coba lagi sebentar.');
        }

        $isi = $respons->json();

        if (! is_array($isi) || ! array_key_exists('ok', $isi)) {
            Log::warning('Absen barcode: balasan tidak dikenali.', ['status' => $respons->status()]);

            return $this->gagal('balasan', 'Balasan aplikasi Absensi tidak dikenali. Hubungi admin sekolah.');
        }

        return $isi;
    }

    private function permintaan(): PendingRequest
    {
        return Http::asForm()->acceptJson()->timeout((int) config('services.absensi.timeout', 8));
    }

    /**
     * @return array{ok: false, kode: string, pesan: string}
     */
    private function gagal(string $kode, string $pesan): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan];
    }
}
