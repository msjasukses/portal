<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Rombel;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class BerkasControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_pdf_material_of_own_class_opens_inline(): void
    {
        Storage::fake('kurikulum')->put('uploads/materi_online/bab1.pdf', '%PDF-1.4 materi');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $materi = MateriOnline::factory()->denganFile('uploads/materi_online/bab1.pdf')->create(['kelas_id' => $rombel->id]);

        $response = $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.materi', $materi));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_html_material_is_always_downloaded(): void
    {
        Storage::fake('kurikulum')->put('uploads/materi_online/halaman.html', '<script>alert(1)</script>');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $materi = MateriOnline::factory()->denganFile('uploads/materi_online/halaman.html')->create(['kelas_id' => $rombel->id]);

        $response = $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.materi', $materi));

        $response->assertOk();
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_material_of_another_class_is_not_found(): void
    {
        Storage::fake('kurikulum')->put('uploads/materi_online/rahasia.pdf', '%PDF-1.4');
        [$siswa] = $this->siswaDiKelas();
        $materi = MateriOnline::factory()->denganFile('uploads/materi_online/rahasia.pdf')->create(['kelas_id' => Rombel::factory()->create()->id]);

        $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.materi', $materi))->assertNotFound();
    }

    public function test_task_attachment_of_own_class_is_downloadable(): void
    {
        Storage::fake('kurikulum')->put('uploads/tugas/soal.docx', 'soal');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id, 'file_lampiran' => 'uploads/tugas/soal.docx']);

        $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.tugas', $tugas))->assertDownload('soal.docx');
    }

    public function test_files_of_another_student_are_not_found(): void
    {
        Storage::fake('kurikulum')->put('uploads/pengumpulan_tugas/teman.pdf', '%PDF-1.4');
        Storage::disk('kurikulum')->put('uploads/jurnal_pkl/teman.jpg', 'foto');
        [$siswa] = $this->siswaDiKelas();
        $teman = Siswa::factory()->create();
        $pengumpulan = PengumpulanTugas::factory()->create(['siswa_id' => $teman->id, 'file' => 'uploads/pengumpulan_tugas/teman.pdf']);
        $jurnal = JurnalPkl::factory()->create(['siswa_id' => $teman->id, 'foto' => 'uploads/jurnal_pkl/teman.jpg']);

        $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.pengumpulan', $pengumpulan))->assertNotFound();
        $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.jurnal', $jurnal))->assertNotFound();
    }

    public function test_missing_file_on_disk_is_not_found(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $materi = MateriOnline::factory()->denganFile('uploads/materi_online/hilang.pdf')->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')->get(route('siswa.berkas.materi', $materi))->assertNotFound();
    }
}
