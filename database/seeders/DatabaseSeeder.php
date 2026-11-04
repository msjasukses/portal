<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Pengaturan tampilan portal. Data siswa, absensi, tugas, dan pelanggaran
     * berasal dari database aplikasi sekolah lain, bukan dari seeder ini.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
        ]);
    }
}
