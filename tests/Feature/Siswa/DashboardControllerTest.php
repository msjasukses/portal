<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\AgendaMengajar;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_shows_current_month_attendance_recap(): void
    {
        $this->travelTo('2026-09-09 10:00');
        [$siswa] = $this->siswaDiKelas();
        $this->jadwalSekolahSeninJumat();
        $this->catatAbsensi($siswa, '2026-09-01', 0, '06:50:00');
        $this->catatAbsensi($siswa, '2026-09-02', 0, '07:10:00');
        $this->catatAbsensi($siswa, '2026-09-03', 2);
        $this->catatAbsensi($siswa, '2026-09-04', 3);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertViewHas('rekapAbsensi', fn (array $rekap) => $rekap['hadir'] === 1
                && $rekap['terlambat'] === 1
                && $rekap['sakit'] === 1
                && $rekap['izin'] === 1
                && $rekap['alpha'] === 3
                && $rekap['magang'] === 0)
            ->assertSee('1 di antaranya terlambat');
    }

    public function test_shows_agenda_tasks_and_materials_of_own_class_only(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $kelasLain = Rombel::factory()->create();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Tugas Kelasku']);
        Tugas::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Tugas Belum Dikerjakan']);
        Tugas::factory()->create(['kelas_id' => $kelasLain->id, 'judul' => 'Tugas Kelas Lain']);
        PengumpulanTugas::factory()->dinilai()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);
        AgendaMengajar::factory()->create(['kelas_id' => $rombel->id, 'materi' => 'Agenda Kelasku']);
        AgendaMengajar::factory()->create(['kelas_id' => $kelasLain->id, 'materi' => 'Agenda Kelas Lain']);
        MateriOnline::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Materi Kelasku']);
        MateriOnline::factory()->create(['kelas_id' => $kelasLain->id, 'judul' => 'Materi Kelas Lain']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertViewHas('tugas', fn (array $tugas) => $tugas['ringkasan'] === ['belum' => 1, 'dikumpulkan' => 1, 'dinilai' => 1])
            ->assertSee(['Tugas Kelasku', 'Agenda Kelasku', 'Materi Kelasku', $rombel->nama_rombel])
            ->assertDontSee(['Tugas Kelas Lain', 'Agenda Kelas Lain', 'Materi Kelas Lain']);
    }

    public function test_still_renders_when_kurikulum_tables_are_unavailable(): void
    {
        [$siswa] = $this->siswaDiKelas();
        Schema::connection('kurikulum')->drop('tugas');
        Schema::connection('kurikulum')->drop('agenda_mengajar');
        Schema::connection('kurikulum')->drop('materi_online');

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee([
                'Data tugas belum dapat dimuat dari aplikasi Kurikulum.',
                'Data agenda belum dapat dimuat dari aplikasi Kurikulum.',
                'Data materi belum dapat dimuat dari aplikasi Kurikulum.',
            ]);
    }
}
