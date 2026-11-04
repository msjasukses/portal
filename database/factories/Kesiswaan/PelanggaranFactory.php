<?php

namespace Database\Factories\Kesiswaan;

use App\Models\Datacenter\Siswa;
use App\Models\Kesiswaan\DataPelanggaran;
use App\Models\Kesiswaan\Pelanggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggaran>
 */
class PelanggaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'data_pelanggaran_id' => DataPelanggaran::factory(),
            'tanggal' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'poin' => fake()->randomElement([5, 10, 15, 25]),
            'keterangan' => fake()->sentence(),
            'tindak_lanjut' => 'Teguran lisan',
        ];
    }
}
