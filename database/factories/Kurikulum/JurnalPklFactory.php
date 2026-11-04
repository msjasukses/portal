<?php

namespace Database\Factories\Kurikulum;

use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JurnalPkl>
 */
class JurnalPklFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'jadwal_magang_id' => JadwalMagang::factory(),
            'tempat' => 'PT '.fake()->company(),
            'tanggal' => today()->toDateString(),
            'kegiatan' => fake()->paragraph(),
            'status' => JurnalPkl::STATUS_MENUNGGU,
        ];
    }

    public function disetujui(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JurnalPkl::STATUS_DISETUJUI,
            'diperiksa_pada' => now(),
        ]);
    }
}
