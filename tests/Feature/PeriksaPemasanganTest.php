<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * `php artisan portal:periksa` — pembeda antara pemasangan yang sehat dan
 * pemasangan yang setengah jadi.
 *
 * Nilai perintah ini seluruhnya terletak pada kode keluarnya: ia dijalankan
 * sesudah memasang di server baru, dan yang dibaca orang adalah "beres" atau
 * "tidak". Perintah yang selalu berkata beres tidak ada gunanya, jadi yang
 * diuji di sini justru bahwa ia berani berkata gagal.
 */
class PeriksaPemasanganTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_a_healthy_install_passes_and_names_what_it_checked(): void
    {
        $this->artisan('portal:periksa')
            ->assertSuccessful()
            ->expectsOutputToContain('Database portal')
            ->expectsOutputToContain('Database Data Center')
            ->expectsOutputToContain('Rilis APK')
            ->expectsOutputToContain('Logo sekolah');
    }

    public function test_an_unreachable_student_database_is_reported_as_a_failure(): void
    {
        // Tanpa database Data Center tidak ada siswa yang bisa masuk, jadi
        // pemasangannya memang belum jadi — dan perintah ini harus
        // mengatakannya dengan kode keluar, bukan hanya dengan tulisan.
        config(['database.connections.datacenter' => [
            'driver' => 'sqlite',
            'database' => '/jalur/yang/pasti/tidak/ada.sqlite',
        ]]);

        DB::purge('datacenter');

        $this->artisan('portal:periksa')->assertFailed();
    }

    public function test_a_school_without_a_release_or_a_logo_is_still_a_working_install(): void
    {
        // Sekolah yang baru dipasang belum mengunggah APK dan belum mengunggah
        // logo. Itu keadaan yang sah: perintahnya mencatat keduanya, tetapi
        // tidak boleh menyatakan pemasangannya gagal.
        config(['portal.rilis_manifes' => storage_path('framework/testing/belum-ada.json')]);

        $this->artisan('portal:periksa')
            ->assertSuccessful()
            ->expectsOutputToContain('belum ada rilis yang diunggah');
    }
}
