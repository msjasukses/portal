<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Siswa;
use App\Models\Kesiswaan\DataPelanggaran;
use App\Models\Kesiswaan\Pelanggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class PelanggaranControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_lists_own_violations_with_total_points(): void
    {
        $siswa = Siswa::factory()->create();
        $terlambat = DataPelanggaran::factory()->create(['nama' => 'Terlambat masuk sekolah']);
        Pelanggaran::factory()->create(['siswa_id' => $siswa->id, 'data_pelanggaran_id' => $terlambat->id, 'poin' => 5]);
        Pelanggaran::factory()->create(['siswa_id' => $siswa->id, 'poin' => 15]);
        Pelanggaran::factory()->create(['poin' => 50, 'keterangan' => 'Catatan siswa lain']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.pelanggaran'))
            ->assertOk()
            ->assertViewHas('totalPoin', 20)
            ->assertSee('Terlambat masuk sekolah')
            ->assertDontSee('Catatan siswa lain');
    }
}
