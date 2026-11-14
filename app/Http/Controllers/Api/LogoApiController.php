<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LogoSekolah;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo sekolah untuk aplikasi ponsel.
 *
 * Tanpa token, sama seperti arahan: logonya dipajang di layar masuk, justru
 * ketika belum ada siswa yang masuk. Berkasnya disajikan lewat portal, bukan
 * ditunjuk ke alamat Data Center, karena Data Center tidak selalu terbuka ke
 * luar dan aplikasi sebaiknya hanya perlu memercayai satu alamat.
 */
class LogoApiController extends Controller
{
    public function __construct(private LogoSekolah $logo) {}

    public function __invoke(): StreamedResponse
    {
        $jalur = $this->logo->jalur();

        abort_unless($jalur !== null, 404, 'Sekolah ini belum mengunggah logo.');

        // Dipajang, bukan diunduh. Lamanya cache satu hari: logo sekolah nyaris
        // tidak pernah berubah, dan yang menggantinya sedang duduk di depan
        // Data Center, bukan menunggu ratusan ponsel menyegarkan diri.
        return $this->logo->disk()->response($jalur, basename($jalur), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
