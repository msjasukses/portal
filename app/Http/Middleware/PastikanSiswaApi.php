<?php

namespace App\Http\Middleware;

use App\Models\SiswaApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API aplikasi ponsel siswa.
 *
 * Token dibaca dari "Authorization: Bearer ...", lalu siswanya dipasang pada
 * guard "siswa" yang sudah dipakai portal di peramban. Dengan begitu seluruh
 * query yang menanyakan $request->user('siswa') berlaku sama persis untuk
 * aplikasi dan untuk halaman web, tanpa satu pun cabang "kalau API".
 */
class PastikanSiswaApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $mentah = $request->bearerToken();

        if (! $mentah) {
            return $this->tolak('Token tidak disertakan. Silakan masuk kembali.');
        }

        $token = SiswaApiToken::where('token', SiswaApiToken::sidik($mentah))->first();

        if (! $token || $token->sudahKedaluwarsa()) {
            $token?->delete();

            return $this->tolak('Sesi Anda sudah berakhir. Silakan masuk kembali.');
        }

        $siswa = $token->siswa;

        if (! $siswa) {
            $token->delete();

            return $this->tolak('Akun Anda tidak ditemukan lagi pada data sekolah.');
        }

        // Akun yang dinonaktifkan atau dikunci di Data Center ikut memutus
        // token yang sudah dipegang; tanpa ini siswa yang sudah lulus masih
        // bisa membuka portal selama tokennya belum kedaluwarsa.
        if (! $siswa->akunAktif()) {
            $token->delete();

            return $this->tolak('Akun siswa tidak aktif. Hubungi admin Data Center.');
        }

        // Ditulis paling sering semenit sekali: dashboard memanggil beberapa
        // endpoint sekaligus, dan tidak ada gunanya menulis pada tiap-tiapnya.
        if (! $token->terakhir_dipakai_at || $token->terakhir_dipakai_at->lt(now()->subMinute())) {
            $token->forceFill(['terakhir_dipakai_at' => now()])->save();
        }

        Auth::guard('siswa')->setUser($siswa);

        // Dipakai endpoint keluar untuk menghapus token yang sedang dipegang,
        // bukan token perangkat lain milik siswa yang sama.
        $request->attributes->set('siswa_api_token', $token);

        return $next($request);
    }

    private function tolak(string $pesan): Response
    {
        return response()->json(['pesan' => $pesan], 401);
    }
}
