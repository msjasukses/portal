<?php

namespace Database\Factories\Kesiswaan;

use App\Models\Kesiswaan\JenisPelanggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisPelanggaran>
 */
class JenisPelanggaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->randomElement(['Kedisiplinan', 'Kerapihan', 'Kelakuan']),
        ];
    }
}
