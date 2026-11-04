<?php

namespace Database\Factories\Kesiswaan;

use App\Models\Kesiswaan\DataPelanggaran;
use App\Models\Kesiswaan\JenisPelanggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataPelanggaran>
 */
class DataPelanggaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_pelanggaran_id' => JenisPelanggaran::factory(),
            'nama' => fake()->randomElement(['Terlambat masuk sekolah', 'Bolos jam pelajaran', 'Tidak memakai atribut']),
            'poin' => 10,
        ];
    }
}
