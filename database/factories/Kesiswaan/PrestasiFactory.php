<?php

namespace Database\Factories\Kesiswaan;

use App\Models\Datacenter\Siswa;
use App\Models\Kesiswaan\Prestasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestasi>
 */
class PrestasiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'nama_prestasi' => 'Juara '.fake()->numberBetween(1, 3).' '.fake()->words(2, true),
            'bidang' => fake()->randomElement(['Akademik', 'Non-Akademik']),
            'tingkat' => fake()->randomElement(['Sekolah', 'Kabupaten/Kota', 'Provinsi', 'Nasional']),
            'peringkat' => 'Juara '.fake()->numberBetween(1, 3),
            'penyelenggara' => fake()->company(),
            'tanggal' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }
}
