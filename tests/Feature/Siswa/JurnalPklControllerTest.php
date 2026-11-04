<?php

namespace Tests\Feature\Siswa;

use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use App\Models\Kurikulum\JurnalPkl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * Hari uji: Rabu 9 September 2026; magang Senin–Jumat sejak Senin 7 September.
 */
class JurnalPklControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    /**
     * @return array{0: Siswa, 1: JadwalMagang}
     */
    private function siswaMagang(): array
    {
        $this->travelTo('2026-09-09 15:00');
        $siswa = Siswa::factory()->create();
        $jadwal = JadwalMagang::factory()->untuk($siswa)->create([
            'tanggal_mulai' => '2026-09-07',
            'tanggal_selesai' => '2026-12-31',
            'tempat' => 'PT Maju Jaya',
        ]);

        return [$siswa, $jadwal];
    }

    /**
     * @param  array<string, mixed>  $isian
     * @return array<string, mixed>
     */
    private function isianJurnal(array $isian = []): array
    {
        return $isian + [
            'tanggal' => '2026-09-09',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kegiatan' => 'Merakit komputer kantor',
        ];
    }

    public function test_lists_internship_days_until_today_with_journal_status(): void
    {
        [$siswa, $jadwal] = $this->siswaMagang();
        JurnalPkl::factory()->create(['siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'tanggal' => '2026-09-08']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.jurnal.index'))
            ->assertOk()
            ->assertViewHas('hariMagang', fn ($hari) => $hari->map(fn ($h) => $h['tanggal']->toDateString())->all() === ['2026-09-09', '2026-09-08', '2026-09-07'])
            ->assertViewHas('jumlahTerisi', 1)
            ->assertSee(['PT Maju Jaya', 'Isi Jurnal Hari Ini', 'Menunggu', 'Belum diisi']);
    }

    public function test_student_saves_journal_on_internship_day_with_photo(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $jadwal] = $this->siswaMagang();

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.jurnal.store'), $this->isianJurnal(['foto' => UploadedFile::fake()->image('kegiatan.jpg')]))
            ->assertRedirect(route('siswa.jurnal.index'))
            ->assertSessionHas('success', 'Jurnal PKL tanggal 09 September 2026 berhasil disimpan.');

        $jurnal = JurnalPkl::sole();
        $this->assertSame($siswa->id, (int) $jurnal->siswa_id);
        $this->assertSame($jadwal->id, (int) $jurnal->jadwal_magang_id);
        $this->assertSame('PT Maju Jaya', $jurnal->tempat);
        $this->assertSame(JurnalPkl::STATUS_MENUNGGU, $jurnal->status);
        $this->assertStringStartsWith('uploads/jurnal_pkl/', $jurnal->foto);
        Storage::disk('kurikulum')->assertExists($jurnal->foto);
    }

    public function test_saving_a_revised_journal_sends_it_back_for_review(): void
    {
        [$siswa, $jadwal] = $this->siswaMagang();
        JurnalPkl::factory()->create([
            'siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'tanggal' => '2026-09-09', 'status' => JurnalPkl::STATUS_REVISI,
        ]);

        $this->actingAs($siswa, 'siswa')->post(route('siswa.jurnal.store'), $this->isianJurnal(['kegiatan' => 'Sudah diperbaiki']));

        $jurnal = JurnalPkl::sole();
        $this->assertSame('Sudah diperbaiki', $jurnal->kegiatan);
        $this->assertSame(JurnalPkl::STATUS_MENUNGGU, $jurnal->status);
    }

    public function test_journal_cannot_be_saved_on_a_non_internship_day(): void
    {
        [$siswa] = $this->siswaMagang();

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.jurnal.store'), $this->isianJurnal(['tanggal' => '2026-09-06']))
            ->assertStatus(422);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.jurnal.form', ['tanggal' => '2026-09-06']))
            ->assertRedirect(route('siswa.jurnal.index'))
            ->assertSessionHas('error', 'Tanggal 06 September 2026 bukan hari magang Anda.');

        $this->assertSame(0, JurnalPkl::count());
    }

    public function test_journal_for_a_future_date_is_rejected(): void
    {
        [$siswa] = $this->siswaMagang();

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.jurnal.store'), $this->isianJurnal(['tanggal' => '2026-09-10']))
            ->assertSessionHasErrors(['tanggal' => 'Jurnal tidak bisa diisi untuk tanggal yang belum terjadi.']);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        [$siswa] = $this->siswaMagang();

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.jurnal.store'), $this->isianJurnal(['jam_mulai' => '16:00', 'jam_selesai' => '08:00']))
            ->assertSessionHasErrors(['jam_selesai' => 'Jam Selesai harus setelah Jam Mulai.']);
    }

    public function test_approved_journal_cannot_be_changed_or_deleted(): void
    {
        [$siswa, $jadwal] = $this->siswaMagang();
        $jurnal = JurnalPkl::factory()->disetujui()->create([
            'siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'tanggal' => '2026-09-09', 'kegiatan' => 'Asli',
        ]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.jurnal.form', ['tanggal' => '2026-09-09']))
            ->assertRedirect(route('siswa.jurnal.show', $jurnal));
        $this->actingAs($siswa, 'siswa')->post(route('siswa.jurnal.store'), $this->isianJurnal())->assertForbidden();
        $this->actingAs($siswa, 'siswa')->delete(route('siswa.jurnal.destroy', $jurnal))->assertForbidden();

        $this->assertSame('Asli', $jurnal->refresh()->kegiatan);
    }

    public function test_journal_of_another_student_is_not_found(): void
    {
        [$siswa] = $this->siswaMagang();
        $jurnalTeman = JurnalPkl::factory()->create();

        $this->actingAs($siswa, 'siswa')->get(route('siswa.jurnal.show', $jurnalTeman))->assertNotFound();
        $this->actingAs($siswa, 'siswa')->delete(route('siswa.jurnal.destroy', $jurnalTeman))->assertNotFound();
        $this->assertModelExists($jurnalTeman);
    }

    public function test_student_deletes_own_pending_journal_and_its_photo(): void
    {
        Storage::fake('kurikulum')->put('uploads/jurnal_pkl/foto.jpg', 'foto');
        [$siswa, $jadwal] = $this->siswaMagang();
        $jurnal = JurnalPkl::factory()->create(['siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'foto' => 'uploads/jurnal_pkl/foto.jpg']);

        $this->actingAs($siswa, 'siswa')
            ->delete(route('siswa.jurnal.destroy', $jurnal))
            ->assertRedirect(route('siswa.jurnal.index'));

        $this->assertModelMissing($jurnal);
        Storage::disk('kurikulum')->assertMissing('uploads/jurnal_pkl/foto.jpg');
    }

    public function test_print_page_lists_journals_of_the_internship(): void
    {
        [$siswa, $jadwal] = $this->siswaMagang();
        JurnalPkl::factory()->create(['siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'tanggal' => '2026-09-07', 'kegiatan' => 'Hari pertama']);
        JurnalPkl::factory()->create(['siswa_id' => $siswa->id, 'jadwal_magang_id' => $jadwal->id, 'tanggal' => '2026-09-08', 'kegiatan' => 'Hari kedua']);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.jurnal.cetak'))
            ->assertOk()
            ->assertSeeInOrder([$siswa->nama_siswa, 'PT Maju Jaya', 'Hari pertama', 'Hari kedua']);
    }
}
