<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\Rombel;
use App\Models\Datacenter\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rombel>
 */
class RombelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tingkat = fake()->numberBetween(10, 12);

        return [
            'nama_rombel' => $tingkat.' '.fake()->unique()->bothify('??? #'),
            'tingkat' => $tingkat,
            'tahun_ajaran_id' => TahunAjaran::factory(),
        ];
    }
}
