<?php

use App\Http\Controllers\Siswa;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Siswa
|--------------------------------------------------------------------------
|
| Login memakai akun siswa aplikasi Data Center (NISN + password). Data
| ditarik dari aplikasi Absensi, Kurikulum, dan Kesiswaan.
|
*/

Route::middleware('guest:siswa')->group(function () {
    Route::get('login', [Siswa\AuthController::class, 'create'])->name('siswa.login');
    Route::post('login', [Siswa\AuthController::class, 'store'])->middleware('throttle:10,1')->name('siswa.login.store');
});

Route::middleware('auth:siswa')->name('siswa.')->group(function () {
    Route::post('logout', [Siswa\AuthController::class, 'destroy'])->name('logout');

    Route::get('/', Siswa\DashboardController::class)->name('dashboard');
    Route::get('absensi', Siswa\AbsensiController::class)->name('absensi');

    // Absen dengan memindai QR Layar Tap aplikasi Absensi.
    Route::get('absen', [Siswa\AbsenBarcodeController::class, 'index'])->name('absen');
    Route::post('absen', [Siswa\AbsenBarcodeController::class, 'store'])
        ->middleware('throttle:20,1')->name('absen.store');

    Route::get('agenda', Siswa\AgendaController::class)->name('agenda');
    Route::get('materi', Siswa\MateriController::class)->name('materi');
    Route::get('prestasi', Siswa\PrestasiController::class)->name('prestasi');
    Route::get('pelanggaran', Siswa\PelanggaranController::class)->name('pelanggaran');

    Route::get('tugas', [Siswa\TugasController::class, 'index'])->name('tugas.index');
    Route::get('tugas/{tugas}', [Siswa\TugasController::class, 'show'])->name('tugas.show');
    Route::post('tugas/{tugas}', [Siswa\TugasController::class, 'store'])->name('tugas.store');

    Route::get('jurnal-pkl', [Siswa\JurnalPklController::class, 'index'])->name('jurnal.index');
    Route::get('jurnal-pkl/isi', [Siswa\JurnalPklController::class, 'form'])->name('jurnal.form');
    Route::post('jurnal-pkl', [Siswa\JurnalPklController::class, 'store'])->name('jurnal.store');
    Route::get('jurnal-pkl/cetak', [Siswa\JurnalPklController::class, 'cetak'])->name('jurnal.cetak');
    Route::get('jurnal-pkl/{jurnal}', [Siswa\JurnalPklController::class, 'show'])->name('jurnal.show');
    Route::delete('jurnal-pkl/{jurnal}', [Siswa\JurnalPklController::class, 'destroy'])->name('jurnal.destroy');

    Route::prefix('berkas')->name('berkas.')->controller(Siswa\BerkasController::class)->group(function () {
        Route::get('materi/{materi}', 'materi')->name('materi');
        Route::get('tugas/{tugas}', 'tugas')->name('tugas');
        Route::get('pengumpulan/{pengumpulan}', 'pengumpulan')->name('pengumpulan');
        Route::get('jurnal/{jurnal}', 'jurnal')->name('jurnal');
    });
});
