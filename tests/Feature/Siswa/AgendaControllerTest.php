<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\AgendaMengajar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class AgendaControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_lists_class_agenda_with_own_attendance_and_hides_rejected_ones(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        AgendaMengajar::factory()->create([
            'kelas_id' => $rombel->id,
            'materi' => 'Persamaan Linear',
            'kehadiran_siswa' => [
                ['siswa_id' => $siswa->id + 1, 'nama' => 'Teman', 'status' => 'Hadir'],
                ['siswa_id' => $siswa->id, 'nama' => $siswa->nama_siswa, 'status' => 'Dispensasi'],
            ],
        ]);
        AgendaMengajar::factory()->ditolak()->create(['kelas_id' => $rombel->id, 'materi' => 'Agenda Ditolak']);
        AgendaMengajar::factory()->create(['kelas_id' => Rombel::factory()->create()->id, 'materi' => 'Agenda Kelas Lain']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.agenda'))
            ->assertOk()
            ->assertSeeInOrder(['Persamaan Linear', 'Dispensasi'])
            ->assertDontSee(['Agenda Ditolak', 'Agenda Kelas Lain']);
    }

    public function test_month_filter_limits_agenda_to_that_month(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        AgendaMengajar::factory()->create(['kelas_id' => $rombel->id, 'waktu_pengisian' => '2026-09-14 08:00:00', 'materi' => 'Materi September']);
        AgendaMengajar::factory()->create(['kelas_id' => $rombel->id, 'waktu_pengisian' => '2026-10-05 08:00:00', 'materi' => 'Materi Oktober']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.agenda', ['bulan' => '2026-09']))
            ->assertOk()
            ->assertSee('Materi September')
            ->assertDontSee('Materi Oktober');
    }

    public function test_rejects_invalid_month_filter(): void
    {
        [$siswa] = $this->siswaDiKelas();

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.agenda', ['bulan' => 'september']))
            ->assertSessionHasErrors(['bulan' => 'Bulan tidak cocok dengan format Y-m.']);
    }
}
