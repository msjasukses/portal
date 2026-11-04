<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guru>
 */
class GuruFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('19##############'),
            'nama_ptk' => fake()->name().', S.Pd.',
        ];
    }
}
