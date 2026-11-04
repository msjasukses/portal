<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Nama & logo sekolah yang tampil di sidebar dan halaman login portal.
     */
    public function run(): void
    {
        Setting::put([
            'site_name' => 'Digital Learning Management System',
            'site_logo' => null,
        ]);
    }
}
