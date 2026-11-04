<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Rombel;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class TugasControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_lists_only_tasks_of_own_class_with_submission_status(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $sudah = Tugas::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Tugas Sudah Dikirim']);
        Tugas::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Tugas Baru']);
        Tugas::factory()->create(['kelas_id' => Rombel::factory()->create()->id, 'judul' => 'Tugas Kelas Lain']);
        PengumpulanTugas::factory()->create(['tugas_id' => $sudah->id, 'siswa_id' => $siswa->id]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.tugas.index'))
            ->assertOk()
            ->assertSeeInOrder(['Tugas Baru', 'Belum dikumpulkan', 'Tugas Sudah Dikirim', 'Sudah dikumpulkan'])
            ->assertDontSee('Tugas Kelas Lain');
    }

    public function test_task_of_another_class_is_forbidden(): void
    {
        [$siswa] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => Rombel::factory()->create()->id]);

        $this->actingAs($siswa, 'siswa')->get(route('siswa.tugas.show', $tugas))->assertForbidden();
        $this->actingAs($siswa, 'siswa')->post(route('siswa.tugas.store', $tugas), ['catatan' => 'Jawaban'])->assertForbidden();
        $this->assertSame(0, PengumpulanTugas::count());
    }

    public function test_submission_stores_answer_file_in_kurikulum_storage(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.tugas.store', $tugas), [
                'catatan' => 'Jawaban saya',
                'file' => UploadedFile::fake()->create('jawaban.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('siswa.tugas.index'))
            ->assertSessionHas('success', 'Tugas berhasil dikumpulkan.');

        $pengumpulan = PengumpulanTugas::where('tugas_id', $tugas->id)->where('siswa_id', $siswa->id)->sole();
        $this->assertSame('Jawaban saya', $pengumpulan->catatan);
        $this->assertFalse($pengumpulan->terlambat);
        $this->assertStringStartsWith('uploads/pengumpulan_tugas/', $pengumpulan->file);
        Storage::disk('kurikulum')->assertExists($pengumpulan->file);
    }

    public function test_submission_after_deadline_is_flagged_late(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->lewatBatas()->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.tugas.store', $tugas), ['catatan' => 'Maaf terlambat'])
            ->assertSessionHas('success', 'Tugas berhasil dikumpulkan, tetapi tercatat melewati batas waktu.');

        $this->assertTrue(PengumpulanTugas::sole()->terlambat);
    }

    public function test_resubmission_replaces_previous_file(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);
        Storage::disk('kurikulum')->put('uploads/pengumpulan_tugas/lama.pdf', 'lama');
        PengumpulanTugas::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id, 'file' => 'uploads/pengumpulan_tugas/lama.pdf']);

        $this->actingAs($siswa, 'siswa')->post(route('siswa.tugas.store', $tugas), [
            'file' => UploadedFile::fake()->create('baru.docx', 50),
        ]);

        Storage::disk('kurikulum')->assertMissing('uploads/pengumpulan_tugas/lama.pdf');
        Storage::disk('kurikulum')->assertExists(PengumpulanTugas::sole()->file);
    }

    public function test_graded_submission_cannot_be_changed(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);
        PengumpulanTugas::factory()->dinilai()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id, 'catatan' => 'Jawaban awal']);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.tugas.store', $tugas), ['catatan' => 'Jawaban diubah'])
            ->assertSessionHas('error', 'Tugas ini sudah dinilai guru, jawabannya tidak bisa diubah lagi.');

        $this->assertSame('Jawaban awal', PengumpulanTugas::sole()->catatan);
    }

    public function test_empty_first_submission_is_rejected(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.tugas.store', $tugas), ['catatan' => ''])
            ->assertSessionHasErrors(['catatan' => 'Isi catatan jawaban atau unggah file terlebih dahulu.']);

        $this->assertSame(0, PengumpulanTugas::count());
    }

    public function test_rejects_answer_file_with_disallowed_type(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.tugas.store', $tugas), ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors(['file' => 'File Jawaban harus berupa file: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, zip, rar, jpg, jpeg, png.']);
    }

    public function test_task_page_renders_teacher_instructions_without_scripts(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create([
            'kelas_id' => $rombel->id,
            'deskripsi' => '<p>Kerjakan <strong>soal 1-5</strong></p><script>alert(1)</script><img src=x onerror=alert(2)>',
        ]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.tugas.show', $tugas))
            ->assertOk()
            ->assertSee('<strong>soal 1-5</strong>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);
    }
}
