<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Number::useLocale(config('app.locale'));

        // Nama & kelas siswa yang login untuk sidebar/topbar Portal Siswa.
        View::composer('layouts.siswa', function ($view) {
            $siswa = auth('siswa')->user();

            $view->with([
                'siswaLogin' => $siswa,
                'kelasSiswa' => $siswa?->rombelAktif()?->nama_rombel,
            ]);
        });
    }
}
