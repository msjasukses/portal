<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('siswa.login'));
        $middleware->redirectUsersTo(fn () => route('siswa.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Database aplikasi sekolah lain tidak bisa dihubungi. Kesalahan tetap
        // dicatat di log; siswa melihat pesan yang jelas.
        $exceptions->render(function (QueryException $e) {
            $aplikasi = [
                'datacenter' => 'Data Center',
                'absensi' => 'Absensi',
                'kurikulum' => 'Kurikulum',
                'kesiswaan' => 'Kesiswaan',
            ][$e->getConnectionName()] ?? null;

            if ($aplikasi) {
                return response()->view('siswa.koneksi-gagal', ['aplikasi' => $aplikasi], 503);
            }
        });
    })->create();
