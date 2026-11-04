<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MataPelajaran>
 */
class MataPelajaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_mapel' => fake()->randomElement(['Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'Informatika', 'Sejarah']),
        ];
    }
}
