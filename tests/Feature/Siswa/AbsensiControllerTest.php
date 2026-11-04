<?php

namespace Tests\Feature\Siswa;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class AbsensiControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_shows_daily_attendance_of_selected_month(): void
    {
        $this->travelTo('2026-10-05 08:00');
        [$siswa] = $this->siswaDiKelas();
        $this->jadwalSekolahSeninJumat();
        $this->catatAbsensi($siswa, '2026-09-15', 0, '06:55:00');
        $this->catatAbsensi($siswa, '2026-09-15', 1, '14:05:00');

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absensi', ['bulan' => '2026-09']))
            ->assertOk()
            ->assertViewHas('laporan', fn (array $laporan) => count($laporan['rows']) === 30 && $laporan['rekap']['hadir'] === 1)
            ->assertSeeInOrder(['15-09-2026', '06:55', '14:05', 'Hadir']);
    }

    public function test_month_choices_start_at_school_year_and_unknown_month_falls_back_to_current(): void
    {
        $this->travelTo('2026-10-05 08:00');
        [$siswa] = $this->siswaDiKelas();

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absensi', ['bulan' => '2020-01']))
            ->assertOk()
            ->assertViewHas('bulan', fn (CarbonImmutable $bulan) => $bulan->format('Y-m') === '2026-10')
            ->assertViewHas('daftarBulan', fn (array $daftar) => array_map(fn ($bulan) => $bulan->format('Y-m'), $daftar)
                === ['2026-10', '2026-09', '2026-08', '2026-07']);
    }
}
