<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiswaResource;
use App\Models\Datacenter\Siswa;
use App\Models\SiswaApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Masuk dan keluar aplikasi ponsel siswa.
 *
 * Kredensialnya sama persis dengan halaman masuk di peramban: NISN dan
 * password akun siswa di Data Center. Aturan akun nonaktif dan kunci sementara
 * juga sama — ditulis sekali di sini mengikuti Siswa\AuthController, supaya
 * tidak ada jalan masuk yang lebih longgar daripada yang lain.
 */
class AuthSiswaApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nisn' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:100'],
            'perangkat' => ['nullable', 'string', 'max:120'],
        ], [], [
            'nisn' => 'NISN',
            'password' => 'Kata sandi',
        ]);

        $siswa = Siswa::where('nisn', $data['nisn'])->first();

        if (! $siswa) {
            $this->gagal('Kombinasi NISN dan kata sandi tidak cocok.');
        }

        if ($siswa->sedangTerkunci()) {
            $menit = max(1, (int) ceil(now()->diffInMinutes($siswa->locked_until)));

            $this->gagal("Akun dikunci sementara karena terlalu banyak percobaan. Coba lagi dalam {$menit} menit.");
        }

        if (! $siswa->akunAktif()) {
            $this->gagal('Akun siswa tidak aktif. Hubungi admin Data Center.');
        }

        // Hash selain bcrypt (mis. password lama yang belum di-hash) dianggap salah.
        $cocok = filled($siswa->password)
            && rescue(fn () => Hash::check($data['password'], $siswa->password), false, report: false);

        if (! $cocok) {
            $gagal = (int) $siswa->failed_login_count + 1;
            $this->catatLogin($siswa, $gagal >= Siswa::MAKS_GAGAL_LOGIN
                ? ['failed_login_count' => 0, 'locked_until' => now()->addMinutes(Siswa::MENIT_KUNCI)]
                : ['failed_login_count' => $gagal]);

            $this->gagal('Kombinasi NISN dan kata sandi tidak cocok.');
        }

        $this->catatLogin($siswa, ['failed_login_count' => 0, 'locked_until' => null, 'last_seen_at' => now()]);

        $mentah = SiswaApiToken::acak();

        SiswaApiToken::create([
            'siswa_id' => $siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'perangkat' => $data['perangkat'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'terakhir_dipakai_at' => now(),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        return response()->json([
            'token' => $mentah,
            'kedaluwarsa_hari' => SiswaApiToken::UMUR_HARI,
            'siswa' => new SiswaResource($siswa),
        ]);
    }

    public function saya(Request $request): JsonResponse
    {
        return response()->json([
            'siswa' => new SiswaResource($request->user('siswa')),
        ]);
    }

    /**
     * Ganti kata sandi akun siswa.
     *
     * Satu-satunya tempat portal menulis ke kolom milik akun Data Center, dan
     * batasnya sengaja sempit: hanya kolom password, hanya milik siswa yang
     * tokennya sedang dipakai, dan hanya setelah sandi lamanya dibuktikan.
     * Portal tetap tidak membuat akun, tidak menghapusnya, dan tidak menyentuh
     * akun siapa pun selain pemegang token.
     *
     * Sandinya sama dengan sandi masuk Data Center dan aplikasi sekolah
     * lainnya, jadi yang berubah di sini berubah di semuanya. Itu disengaja:
     * siswa hanya punya satu kata sandi, dan menyimpan dua sandi berbeda untuk
     * akun yang sama adalah cara tercepat membuatnya terkunci.
     */
    public function gantiSandi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sandi_lama' => ['required', 'string', 'max:100'],
            'sandi_baru' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ], [], [
            'sandi_lama' => 'Kata sandi saat ini',
            'sandi_baru' => 'Kata sandi baru',
        ]);

        $siswa = $request->user('siswa');

        // Hash selain bcrypt dianggap tidak cocok, sama seperti saat masuk.
        $cocok = filled($siswa->password)
            && rescue(fn () => Hash::check($data['sandi_lama'], $siswa->password), false, report: false);

        if (! $cocok) {
            throw ValidationException::withMessages([
                'sandi_lama' => 'Kata sandi saat ini salah.',
            ]);
        }

        if (rescue(fn () => Hash::check($data['sandi_baru'], $siswa->password), false, report: false)) {
            throw ValidationException::withMessages([
                'sandi_baru' => 'Kata sandi baru harus berbeda dari yang sekarang.',
            ]);
        }

        // Kegagalan menulis tidak boleh diam. Penanda login memang sengaja
        // dilewati bila user database Data Center hanya punya hak baca, tetapi
        // siswa yang mengira sandinya sudah berganti padahal tidak akan
        // terkunci dari seluruh aplikasi sekolah pada percobaan masuk
        // berikutnya — dan tidak ada yang bisa menduga sebabnya.
        $tersimpan = rescue(
            fn () => $siswa->forceFill(['password' => Hash::make($data['sandi_baru'])])->save(),
            false,
        );

        if (! $tersimpan) {
            return response()->json([
                'pesan' => 'Kata sandi tidak bisa disimpan ke Data Center sekolah. '
                    .'Sandi lama Anda masih berlaku. Beri tahu operator sekolah.',
            ], 503);
        }

        // Perangkat lain ikut dikeluarkan. Sandi yang diganti karena orang lain
        // mengetahuinya tidak ada gunanya bila ponsel orang itu masih memegang
        // token yang sah. Token perangkat ini sengaja dibiarkan: siswa yang
        // baru saja membuktikan sandi lamanya tidak perlu disuruh masuk lagi
        // di tempat ia berdiri.
        $ini = $request->attributes->get('siswa_api_token');

        rescue(fn () => SiswaApiToken::where('siswa_id', $siswa->id)
            ->when($ini, fn ($q) => $q->whereKeyNot($ini->getKey()))
            ->delete());

        return response()->json([
            'pesan' => 'Kata sandi berhasil diubah. Pakai yang baru untuk masuk ke '
                .'portal, aplikasi ujian, dan Data Center.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Hanya token perangkat ini yang dihapus. Siswa yang membuka portal di
        // HP dan tablet tidak ikut terlempar keluar dari yang satunya.
        $request->attributes->get('siswa_api_token')?->delete();

        return response()->json(['pesan' => 'Anda sudah keluar.']);
    }

    /**
     * Pesan ditempelkan pada kolom NISN supaya aplikasi bisa menampilkannya
     * dengan cara yang sama seperti galat validasi lainnya.
     *
     * @throws ValidationException
     */
    private function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['nisn' => $pesan]);
    }

    /**
     * Catat penanda login di Data Center. Bila user database Data Center hanya
     * punya hak baca, pencatatan dilewati tanpa menggagalkan login.
     *
     * @param  array<string, mixed>  $kolom
     */
    private function catatLogin(Siswa $siswa, array $kolom): void
    {
        rescue(fn () => $siswa->forceFill($kolom)->save());
    }
}
