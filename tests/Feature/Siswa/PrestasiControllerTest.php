<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Siswa;
use App\Models\Kesiswaan\Prestasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class PrestasiControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_lists_only_own_achievements(): void
    {
        $siswa = Siswa::factory()->create();
        Prestasi::factory()->create(['siswa_id' => $siswa->id, 'nama_prestasi' => 'Juara 1 Olimpiade Matematika', 'bidang' => 'Akademik']);
        Prestasi::factory()->create(['nama_prestasi' => 'Prestasi Siswa Lain']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.prestasi'))
            ->assertOk()
            ->assertSee('Juara 1 Olimpiade Matematika')
            ->assertDontSee('Prestasi Siswa Lain')
            ->assertViewHas('perBidang', fn ($perBidang) => $perBidang->all() === ['Akademik' => 1]);
    }

    public function test_shows_notice_when_kesiswaan_data_cannot_be_loaded(): void
    {
        Schema::connection('kesiswaan')->drop('prestasi');

        $this->actingAs(Siswa::factory()->create(), 'siswa')
            ->get(route('siswa.prestasi'))
            ->assertStatus(503)
            ->assertSee('Aplikasi Kesiswaan sedang tidak dapat dihubungi.');
    }
}
