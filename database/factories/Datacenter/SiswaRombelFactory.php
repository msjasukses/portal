<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\Rombel;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\SiswaRombel;
use App\Models\Datacenter\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiswaRombel>
 */
class SiswaRombelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'rombongan_belajar_id' => Rombel::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
        ];
    }
}
