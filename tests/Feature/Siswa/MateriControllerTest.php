<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\MateriOnline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class MateriControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_lists_materials_of_own_class_with_content_markers_and_links(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $materi = MateriOnline::factory()->denganFile()->denganVideo()->create(['kelas_id' => $rombel->id, 'judul' => 'Sistem Persamaan']);
        MateriOnline::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Materi Kosong', 'created_at' => now()->subMonth()]);
        MateriOnline::factory()->create(['kelas_id' => Rombel::factory()->create()->id, 'judul' => 'Materi Kelas Lain']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.materi'))
            ->assertOk()
            ->assertSeeInOrder(['Sistem Persamaan', 'File', 'Video', 'Baru'])
            ->assertSeeInOrder(['Materi Kosong', 'Belum ada lampiran', 'Lihat saja'])
            ->assertSee(route('siswa.berkas.materi', $materi), false)
            ->assertSee('href="https://www.youtube.com/watch?v=abc123"', false)
            ->assertDontSee('Materi Kelas Lain');
    }

    public function test_search_filters_materials_by_title(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        MateriOnline::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Aljabar Linear']);
        MateriOnline::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Teks Prosedur']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.materi', ['q' => 'aljabar']))
            ->assertSee('Aljabar Linear')
            ->assertDontSee('Teks Prosedur');
    }

    public function test_non_http_video_link_is_not_rendered_as_link(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        MateriOnline::factory()->denganVideo('javascript:alert(1)')->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.materi'))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }
}
