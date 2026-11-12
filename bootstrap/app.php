<?php

use App\Http\Middleware\PastikanSiswaApi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('siswa.login'));
        $middleware->redirectUsersTo(fn () => route('siswa.dashboard'));

        $middleware->alias(['siswa.api' => PastikanSiswaApi::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Database aplikasi sekolah lain tidak bisa dihubungi. Kesalahan tetap
        // dicatat di log; siswa melihat pesan yang jelas.
        $exceptions->render(function (QueryException $e, Request $request) {
            $aplikasi = [
                'datacenter' => 'Data Center',
                'absensi' => 'Absensi',
                'kurikulum' => 'Kurikulum',
                'kesiswaan' => 'Kesiswaan',
            ][$e->getConnectionName()] ?? null;

            if (! $aplikasi) {
                return null;
            }

            $pesan = "Data dari aplikasi {$aplikasi} belum dapat dimuat. Coba lagi beberapa saat lagi.";

            // Aplikasi ponsel tidak bisa membaca halaman blade; tanpa cabang
            // ini ia menerima HTML dan melaporkannya sebagai galat tak dikenal.
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['pesan' => $pesan, 'aplikasi' => $aplikasi], 503)
                : response()->view('siswa.koneksi-gagal', ['aplikasi' => $aplikasi], 503);
        });
    })->create();
