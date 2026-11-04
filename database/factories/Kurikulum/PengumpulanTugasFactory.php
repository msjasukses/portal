<?php

namespace Database\Factories\Kurikulum;

use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengumpulanTugas>
 */
class PengumpulanTugasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tugas_id' => Tugas::factory(),
            'siswa_id' => Siswa::factory(),
            'catatan' => fake()->sentence(),
            'dikumpulkan_pada' => now(),
            'terlambat' => false,
        ];
    }

    /** Sudah diberi nilai oleh guru. */
    public function dinilai(int $nilai = 85): static
    {
        return $this->state(fn (array $attributes) => [
            'nilai' => $nilai,
            'umpan_balik' => 'Bagus, pertahankan.',
            'dinilai_pada' => now(),
        ]);
    }
}
