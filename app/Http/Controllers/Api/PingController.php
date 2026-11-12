<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Penanda bahwa alamat yang diketik operator memang Portal Siswa.
 *
 * Dipanggil aplikasi ponsel sebelum siswa diminta mengetik NISN-nya. Tanpa ini
 * salah ketik alamat baru ketahuan di layar masuk, dan siswa membacanya sebagai
 * "kata sandi saya salah" — pertanyaan yang sulit dijawab di tengah pagi.
 *
 * Satu domain sekolah sering memuat beberapa aplikasi, jadi balasannya harus
 * cukup untuk membedakan portal dari aplikasi tetangganya.
 */
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'aplikasi' => 'Portal Siswa',
            'versi_api' => 1,
            'waktu' => now()->toIso8601String(),
        ]);
    }
}
