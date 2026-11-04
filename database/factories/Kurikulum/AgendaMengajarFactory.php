<?php

namespace Database\Factories\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\AgendaMengajar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaMengajar>
 */
class AgendaMengajarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guru_id' => Guru::factory(),
            'mengajar_sebagai' => 'normal',
            'waktu_pengisian' => now(),
            'jam_ke' => '1',
            'jumlah_jam' => 2,
            'kelas_id' => Rombel::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'materi' => fake()->sentence(),
            'status' => 'Sedang Ditinjau',
        ];
    }

    /** Agenda yang ditolak admin saat validasi. */
    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgendaMengajar::STATUS_DITOLAK,
        ]);
    }
}
