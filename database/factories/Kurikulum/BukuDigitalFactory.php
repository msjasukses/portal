<?php

namespace Database\Factories\Kurikulum;

use App\Models\Kurikulum\BukuDigital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Buku katalog yang berkasnya masih di SIBI; pakai state diunduh() untuk buku
 * yang berkasnya sudah ada di penyimpanan sekolah.
 *
 * @extends Factory<BukuDigital>
 */
class BukuDigitalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sibi_id' => (string) fake()->unique()->numberBetween(1000, 9999),
            'slug' => fake()->slug(3),
            'sumber_feed' => 'kurikulum-merdeka',
            'judul' => fake()->sentence(4),
            'penulis' => fake()->name(),
            'penerbit' => 'Kemendikdasmen',
            'jenjang' => 'SMP/MTS',
            'tingkat_kelas' => 'VII',
            'mata_pelajaran' => 'Informatika',
            'kategori' => 'Buku Teks',
            'jenis_buku' => 'Buku Siswa',
            'kurikulum' => 'Merdeka',
            'tipe' => 'pdf',
            'url_cover' => 'https://buku.kemendikdasmen.go.id/cover/contoh.png',
            'url_file' => 'https://buku.kemendikdasmen.go.id/file/contoh.pdf',
        ];
    }

    /** Berkasnya sudah diunduh sekolah, jadi dibaca lewat portal. */
    public function diunduh(string $path = 'buku-digital/file/contoh.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => $path,
            'ukuran_file' => 4_497_251,
            'diunduh_pada' => now(),
        ]);
    }
}
