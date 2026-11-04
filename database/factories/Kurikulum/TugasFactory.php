<?php

namespace Database\Factories\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\Tugas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tugas yang masih dalam batas waktu.
 *
 * @extends Factory<Tugas>
 */
class TugasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(3),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'kelas_id' => Rombel::factory(),
            'pegawai_id' => Guru::factory(),
            'deskripsi' => '<p>'.fake()->sentence().'</p>',
            'tanggal_mulai' => today()->subDays(3)->toDateString(),
            'tanggal_selesai' => today()->addDays(3)->toDateString(),
        ];
    }

    public function lewatBatas(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_selesai' => today()->subDay()->toDateString(),
        ]);
    }
}
