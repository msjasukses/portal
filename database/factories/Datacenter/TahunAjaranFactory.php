<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $awal = fake()->unique()->numberBetween(2020, 2040);

        return [
            'kode_tahun_ajaran' => substr((string) $awal, 2).substr((string) ($awal + 1), 2),
            'nama_tahun_ajaran' => $awal.'/'.($awal + 1),
            'tanggal_mulai' => $awal.'-07-13',
            'tanggal_selesai' => ($awal + 1).'-06-30',
            'is_aktif' => false,
        ];
    }

    /** Tahun ajaran aktif yang mencakup hari ini. */
    public function aktif(): static
    {
        return $this->state(function (array $attributes) {
            $awal = today()->month >= 7 ? today()->year : today()->year - 1;

            return [
                'kode_tahun_ajaran' => substr((string) $awal, 2).substr((string) ($awal + 1), 2),
                'nama_tahun_ajaran' => $awal.'/'.($awal + 1),
                'tanggal_mulai' => $awal.'-07-01',
                'tanggal_selesai' => ($awal + 1).'-06-30',
                'is_aktif' => true,
            ];
        });
    }
}
