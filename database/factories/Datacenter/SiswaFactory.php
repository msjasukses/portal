<?php

namespace Database\Factories\Datacenter;

use App\Models\Datacenter\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Akun siswa di Data Center. Password bawaan: "password".
 *
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nisn' => fake()->unique()->numerify('32##########'),
            'nis' => fake()->unique()->numerify('######'),
            'nama_siswa' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'password' => static::$password ??= Hash::make('password'),
            'is_aktif' => true,
            'status_siswa' => 'Aktif',
            'account_status' => 'active',
            'failed_login_count' => 0,
        ];
    }

    /** Akun dikunci sementara setelah terlalu banyak salah password. */
    public function terkunci(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_until' => now()->addMinutes(10),
        ]);
    }

    /** Akun dinonaktifkan admin Data Center. */
    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'suspended',
        ]);
    }

    /** Siswa tanpa NIS — aplikasi Absensi memakai NISN sebagai kuncinya. */
    public function tanpaNis(): static
    {
        return $this->state(fn (array $attributes) => [
            'nis' => null,
        ]);
    }
}
