<?php

namespace Database\Factories\Absensi;

use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Jadwal magang Senin–Jumat yang sedang berjalan.
 *
 * @extends Factory<JadwalMagang>
 */
class JadwalMagangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => fake()->numerify('######'),
            'tanggal_mulai' => today()->subMonth()->toDateString(),
            'tanggal_selesai' => today()->addMonth()->toDateString(),
            'hari' => '1,2,3,4,5',
            'tempat' => 'PT '.fake()->company(),
            'pembimbing' => fake()->name(),
        ];
    }

    public function untuk(Siswa $siswa): static
    {
        return $this->state(fn (array $attributes) => [
            'nis' => $siswa->kunciAbsensi(),
        ]);
    }
}
