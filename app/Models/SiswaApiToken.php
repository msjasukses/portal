<?php

namespace App\Models;

use App\Models\Datacenter\Siswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token masuk aplikasi ponsel siswa, pengganti sesi peramban.
 *
 * Berbeda dengan aplikasi ujian, portal tidak membatasi satu akun satu
 * perangkat. Portal hanya memajang informasi — siswa yang membukanya di HP dan
 * di tablet tidak sedang menyontek, dan memutus salah satunya hanya
 * membingungkan.
 */
class SiswaApiToken extends Model
{
    /**
     * Umur token. Aplikasi dipasang sekali lalu dipakai berbulan-bulan, jadi
     * masa berlakunya panjang; yang memutus lebih awal adalah tombol keluar.
     */
    public const UMUR_HARI = 60;

    protected $table = 'siswa_api_token';

    protected $fillable = [
        'siswa_id',
        'token',
        'perangkat',
        'ip_address',
        'user_agent',
        'terakhir_dipakai_at',
        'kedaluwarsa_at',
    ];

    protected $hidden = ['token'];

    /** Yang masuk database hanyalah sidiknya; mentahnya hanya sekali dikirim. */
    public static function sidik(string $mentah): string
    {
        return hash('sha256', $mentah);
    }

    public static function acak(): string
    {
        return Str::random(64);
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function sudahKedaluwarsa(): bool
    {
        return $this->kedaluwarsa_at !== null && $this->kedaluwarsa_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terakhir_dipakai_at' => 'datetime',
            'kedaluwarsa_at' => 'datetime',
        ];
    }
}
