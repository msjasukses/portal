<?php

namespace Database\Factories\Kurikulum;

use App\Models\Datacenter\Guru;
use App\Models\Datacenter\MataPelajaran;
use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\MateriOnline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Materi tanpa lampiran; pakai state denganFile()/denganVideo() bila perlu.
 *
 * @extends Factory<MateriOnline>
 */
class MateriOnlineFactory extends Factory
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
        ];
    }

    public function denganFile(string $path = 'uploads/materi_online/materi.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'file' => $path,
        ]);
    }

    public function denganVideo(string $tautan = 'https://www.youtube.com/watch?v=abc123'): static
    {
        return $this->state(fn (array $attributes) => [
            'link_video' => $tautan,
        ]);
    }
}
