<?php

namespace Database\Factories\Kurikulum;

use App\Models\Datacenter\MataPelajaran;
use App\Models\Kurikulum\ModulAjar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModulAjar>
 */
class ModulAjarFactory extends Factory
{
    /**
     * Modul hasil Generator: bagian-bagiannya terisi, tanpa berkas unggahan.
     *
     * Bagian asesmen sengaja diisi di sini — justru supaya pengujian bisa
     * membuktikan bahwa ketiganya tidak pernah sampai ke aplikasi siswa.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul' => 'Modul '.fake()->words(3, true),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'fase' => 'F',
            'jumlah_jam' => 2,
            'pertemuan_ke' => 3,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'deskripsi' => fake()->sentence(),
            'tujuan_pembelajaran' => '<p>Siswa dapat menjelaskan alur data.</p>',
            'capaian_pembelajaran' => '<p>Memahami dasar jaringan komputer.</p>',
            'pertanyaan_pemantik' => '<p>Bagaimana pesan sampai ke ponsel?</p>',
            'kegiatan_inti' => '<p>Diskusi kelompok lalu presentasi.</p>',
            'lkpd_petunjuk' => '<p>Kerjakan berpasangan.</p>',
            'asesmen_formatif' => '<p>Kunci: B, lalu D.</p>',
            'asesmen_sumatif' => '<p>Rubrik penilaian akhir.</p>',
            'asesmen_diagnostik' => '<p>Pretest beserta jawabannya.</p>',
            'lkpd_asesmen' => '<p>Rubrik LKPD.</p>',
        ];
    }

    /** Modul yang diunggah guru sebagai satu berkas, tanpa bagian terisi. */
    public function unggahan(string $path = 'modul-ajar/contoh.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'file' => $path,
            'file_nama' => basename($path),
            'file_ukuran' => 1536000,
            'tujuan_pembelajaran' => null,
            'capaian_pembelajaran' => null,
            'pertanyaan_pemantik' => null,
            'kegiatan_inti' => null,
            'lkpd_petunjuk' => null,
        ]);
    }
}
