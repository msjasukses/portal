<?php

/*
|--------------------------------------------------------------------------
| API Portal Siswa — aplikasi ponsel
|--------------------------------------------------------------------------
| Awalan otomatis: /api (lihat bootstrap/app.php)
|
| Melayani satu klien saja: aplikasi ponsel siswa. Isinya cermin dari menu
| Portal Siswa di peramban — controller, query, dan aturannya sama, hanya
| keluarannya JSON. Tidak ada endpoint pengelolaan di sini, dan tidak boleh
| ada: guru dan admin tetap memakai aplikasi masing-masing.
|
| Autentikasi memakai token bearer yang diterbitkan POST /api/v1/auth/login,
| dijaga middleware "siswa.api". Akun siswanya tetap milik Data Center —
| portal tidak pernah membuat atau mengubah akun.
*/

use App\Http\Controllers\Api;
use App\Http\Controllers\Siswa;
use Illuminate\Support\Facades\Route;

// Dipakai aplikasi untuk memastikan alamat yang diketik operator memang portal
// ini, sebelum siswa diminta mengetik NISN-nya.
Route::get('/ping', Api\PingController::class)->name('api.ping');

Route::prefix('v1')->name('api.')->group(function () {

    // ---- Tanpa token ----
    Route::get('arahan', Api\ArahanController::class)->name('arahan');
    Route::post('auth/login', [Api\AuthSiswaApiController::class, 'login'])
        ->middleware('throttle:20,1')->name('auth.login');

    // ---- Butuh token siswa ----
    Route::middleware('siswa.api')->group(function () {

        Route::get('auth/saya', [Api\AuthSiswaApiController::class, 'saya'])->name('auth.saya');
        Route::post('auth/logout', [Api\AuthSiswaApiController::class, 'logout'])->name('auth.logout');

        Route::get('dashboard', Api\DashboardApiController::class)->name('dashboard');

        Route::get('absensi', Api\AbsensiApiController::class)->name('absensi');
        Route::get('absen', [Api\AbsenBarcodeApiController::class, 'show'])->name('absen');
        Route::post('absen', [Api\AbsenBarcodeApiController::class, 'store'])
            ->middleware('throttle:20,1')->name('absen.store');

        Route::get('agenda', Api\AgendaApiController::class)->name('agenda');
        Route::get('modul-ajar/{modul}', Api\ModulAjarApiController::class)->name('modul-ajar');
        Route::get('materi', Api\MateriApiController::class)->name('materi');
        Route::get('prestasi', Api\PrestasiApiController::class)->name('prestasi');
        Route::get('pelanggaran', Api\PelanggaranApiController::class)->name('pelanggaran');

        Route::get('tugas', [Api\TugasApiController::class, 'index'])->name('tugas.index');
        Route::get('tugas/{tugas}', [Api\TugasApiController::class, 'show'])->name('tugas.show');
        Route::post('tugas/{tugas}', [Api\TugasApiController::class, 'store'])->name('tugas.store');

        // Katalog buku digital. Siswa menelusuri dan membaca; mengunduh dari
        // SIBI dan menyinkron katalog tidak ada di sini — di peramban pun
        // keduanya hak guru dan admin.
        Route::get('katalog-buku', [Api\KatalogBukuApiController::class, 'index'])->name('buku.index');
        Route::get('katalog-buku/{buku}', [Api\KatalogBukuApiController::class, 'show'])->name('buku.show');

        Route::get('jurnal-pkl', [Api\JurnalPklApiController::class, 'index'])->name('jurnal.index');
        Route::post('jurnal-pkl', [Api\JurnalPklApiController::class, 'store'])->name('jurnal.store');

        // Jurnal siap cetak: satu-satunya balasan HTML di sini, dan sengaja
        // memakai aksi yang sama dengan tombol Cetak di peramban — isi lembar
        // cetaknya tidak boleh mungkin berbeda antara aplikasi dan portal.
        // Harus di atas "jurnal-pkl/{jurnal}" supaya "cetak" tidak dibaca id.
        Route::get('jurnal-pkl/cetak', [Siswa\JurnalPklController::class, 'cetak'])->name('jurnal.cetak');

        Route::get('jurnal-pkl/{jurnal}', [Api\JurnalPklApiController::class, 'show'])->name('jurnal.show');
        Route::delete('jurnal-pkl/{jurnal}', [Api\JurnalPklApiController::class, 'destroy'])->name('jurnal.destroy');

        Route::prefix('berkas')->name('berkas.')->controller(Api\BerkasApiController::class)->group(function () {
            Route::get('materi/{materi}', 'materi')->name('materi');
            Route::get('tugas/{tugas}', 'tugas')->name('tugas');
            Route::get('pengumpulan/{pengumpulan}', 'pengumpulan')->name('pengumpulan');
            Route::get('jurnal/{jurnal}', 'jurnal')->name('jurnal');
            Route::get('buku/{buku}', 'buku')->name('buku');
            Route::get('buku-cover/{buku}', 'bukuCover')->name('buku-cover');
            Route::get('modul-ajar/{modul}', 'modulAjar')->name('modul-ajar');
        });
    });
});
