<?php

namespace Tests\Feature\Api;

use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Rombel;
use App\Models\Datacenter\Siswa;
use App\Models\Kesiswaan\Pelanggaran;
use App\Models\Kesiswaan\Prestasi;
use App\Models\Kurikulum\AgendaMengajar;
use App\Models\Kurikulum\BukuDigital;
use App\Models\Kurikulum\JurnalPkl;
use App\Models\Kurikulum\MateriOnline;
use App\Models\Kurikulum\ModulAjar;
use App\Models\Kurikulum\PengumpulanTugas;
use App\Models\Kurikulum\Tugas;
use App\Models\SiswaApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * Endpoint modul: absensi, kurikulum, dan kesiswaan.
 *
 * Yang dijaga ketat di sini satu hal — aplikasi hanya boleh memperlihatkan
 * data milik siswa yang memegang tokennya, dan aturan menulisnya sama dengan
 * halaman web. Jalur HTTP boleh baru; batasannya tidak boleh ikut baru.
 */
class PortalApiTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    private const TOKEN_BARCODE = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function test_only_own_achievements_and_violations_are_returned(): void
    {
        $siswa = Siswa::factory()->create();
        Prestasi::factory()->create(['siswa_id' => $siswa->id, 'nama_prestasi' => 'Juara 1 Olimpiade', 'bidang' => 'Akademik']);
        Prestasi::factory()->create(['nama_prestasi' => 'Prestasi Siswa Lain']);
        Pelanggaran::factory()->create(['siswa_id' => $siswa->id, 'poin' => 15]);
        Pelanggaran::factory()->create(['poin' => 99]);

        $this->sebagai($siswa)->getJson(route('api.prestasi'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Juara 1 Olimpiade')
            ->assertJsonPath('rekap.per_bidang.Akademik', 1);

        $this->sebagai($siswa)->getJson(route('api.pelanggaran'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('rekap.total_poin', 15);
    }

    public function test_only_tasks_and_materials_of_own_class_are_returned(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $kelasLain = Rombel::factory()->create();

        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Tugas Kelas Saya']);
        Tugas::factory()->create(['kelas_id' => $kelasLain->id, 'judul' => 'Tugas Kelas Lain']);
        PengumpulanTugas::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id, 'catatan' => 'Jawaban saya']);

        MateriOnline::factory()->create(['kelas_id' => $rombel->id, 'judul' => 'Materi Kelas Saya']);
        MateriOnline::factory()->create(['kelas_id' => $kelasLain->id, 'judul' => 'Materi Kelas Lain']);

        $this->sebagai($siswa)->getJson(route('api.tugas.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Tugas Kelas Saya')
            ->assertJsonPath('data.0.pengumpulan.catatan', 'Jawaban saya');

        $this->sebagai($siswa)->getJson(route('api.materi'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Materi Kelas Saya');
    }

    public function test_task_of_another_class_is_forbidden(): void
    {
        [$siswa] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => Rombel::factory()->create()->id]);

        $this->sebagai($siswa)->getJson(route('api.tugas.show', $tugas))->assertForbidden();
        $this->sebagai($siswa)->postJson(route('api.tugas.store', $tugas), ['catatan' => 'Jawaban'])->assertForbidden();

        $this->assertSame(0, PengumpulanTugas::count());
    }

    public function test_submission_stores_the_answer_file_in_kurikulum_storage(): void
    {
        Storage::fake('kurikulum');
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);

        $this->sebagai($siswa)
            ->post(route('api.tugas.store', $tugas), [
                'catatan' => 'Jawaban saya',
                'file' => UploadedFile::fake()->create('jawaban.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('pesan', 'Tugas berhasil dikumpulkan.');

        $pengumpulan = PengumpulanTugas::sole();
        $this->assertSame('Jawaban saya', $pengumpulan->catatan);
        $this->assertFalse((bool) $pengumpulan->terlambat);
        $this->assertStringStartsWith('uploads/pengumpulan_tugas/', $pengumpulan->file);
        Storage::disk('kurikulum')->assertExists($pengumpulan->file);
    }

    public function test_graded_submission_can_no_longer_be_changed(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();
        $tugas = Tugas::factory()->create(['kelas_id' => $rombel->id]);
        PengumpulanTugas::factory()->create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $siswa->id,
            'catatan' => 'Jawaban asli',
            'nilai' => 90,
        ]);

        $this->sebagai($siswa)
            ->postJson(route('api.tugas.store', $tugas), ['catatan' => 'Jawaban baru'])
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Tugas ini sudah dinilai guru, jawabannya tidak bisa diubah lagi.');

        $this->assertSame('Jawaban asli', PengumpulanTugas::sole()->catatan);
    }

    public function test_journal_can_only_be_filled_on_an_internship_day(): void
    {
        $this->travelTo('2026-09-09 15:00');
        $siswa = Siswa::factory()->create();
        JadwalMagang::factory()->untuk($siswa)->create([
            'tanggal_mulai' => '2026-09-07',
            'tanggal_selesai' => '2026-12-31',
            'tempat' => 'PT Maju Jaya',
        ]);

        // Sabtu 5 September: di luar periode sekaligus bukan hari magang.
        $this->sebagai($siswa)
            ->postJson(route('api.jurnal.store'), ['tanggal' => '2026-09-05', 'kegiatan' => 'Merakit komputer'])
            ->assertStatus(422);

        // 201 pada jurnal baru, 200 saat jurnal hari itu diperbaiki — perilaku
        // bawaan Eloquent API Resource, dan keduanya 2xx bagi aplikasi.
        $this->sebagai($siswa)
            ->postJson(route('api.jurnal.store'), ['tanggal' => '2026-09-09', 'kegiatan' => 'Merakit komputer'])
            ->assertCreated()
            ->assertJsonPath('data.status', JurnalPkl::STATUS_MENUNGGU)
            ->assertJsonPath('data.tempat', 'PT Maju Jaya');

        $this->sebagai($siswa)
            ->postJson(route('api.jurnal.store'), ['tanggal' => '2026-09-09', 'kegiatan' => 'Merakit komputer kantor'])
            ->assertOk()
            ->assertJsonPath('data.kegiatan', 'Merakit komputer kantor');

        $this->assertSame(1, JurnalPkl::count());
    }

    public function test_approved_journal_can_neither_be_changed_nor_deleted(): void
    {
        $this->travelTo('2026-09-09 15:00');
        $siswa = Siswa::factory()->create();
        $jadwal = JadwalMagang::factory()->untuk($siswa)->create([
            'tanggal_mulai' => '2026-09-07',
            'tanggal_selesai' => '2026-12-31',
        ]);
        $jurnal = JurnalPkl::factory()->create([
            'siswa_id' => $siswa->id,
            'jadwal_magang_id' => $jadwal->id,
            'tanggal' => '2026-09-09',
            'kegiatan' => 'Kegiatan yang sudah disetujui',
            'status' => JurnalPkl::STATUS_DISETUJUI,
        ]);

        $this->sebagai($siswa)
            ->postJson(route('api.jurnal.store'), ['tanggal' => '2026-09-09', 'kegiatan' => 'Diubah'])
            ->assertForbidden();

        $this->sebagai($siswa)->deleteJson(route('api.jurnal.destroy', $jurnal))->assertForbidden();

        $this->assertSame('Kegiatan yang sudah disetujui', JurnalPkl::sole()->kegiatan);
    }

    public function test_another_students_journal_is_treated_as_missing(): void
    {
        $siswa = Siswa::factory()->create();
        $jurnal = JurnalPkl::factory()->create(['siswa_id' => Siswa::factory()->create()->id]);

        $this->sebagai($siswa)->getJson(route('api.jurnal.show', $jurnal))->assertNotFound();
        $this->sebagai($siswa)->deleteJson(route('api.jurnal.destroy', $jurnal))->assertNotFound();
    }

    public function test_printable_journal_sheet_only_holds_the_students_own_period(): void
    {
        $this->travelTo('2026-09-09 15:00');
        $siswa = Siswa::factory()->create(['nama_siswa' => 'Siti Aminah']);
        $jadwal = JadwalMagang::factory()->untuk($siswa)->create([
            'tanggal_mulai' => '2026-09-07',
            'tanggal_selesai' => '2026-12-31',
            'tempat' => 'PT Maju Jaya',
        ]);

        JurnalPkl::factory()->create([
            'siswa_id' => $siswa->id,
            'jadwal_magang_id' => $jadwal->id,
            'tanggal' => '2026-09-09',
            'kegiatan' => 'Merakit komputer kantor',
        ]);

        // Jurnal dari periode magang lain tidak boleh ikut ke lembar ini.
        JurnalPkl::factory()->create([
            'siswa_id' => $siswa->id,
            'jadwal_magang_id' => $jadwal->id + 99,
            'tanggal' => '2026-09-10',
            'kegiatan' => 'Kegiatan periode lain',
        ]);

        $this->sebagai($siswa)
            ->get(route('api.jurnal.cetak', ['jadwal_id' => $jadwal->id]))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('PT Maju Jaya')
            ->assertSee('Merakit komputer kantor')
            ->assertDontSee('Kegiatan periode lain');

        // Alamatnya ikut dibawa daftar jurnal, supaya aplikasi tidak perlu
        // menyusun sendiri alamat endpoint portal.
        $this->sebagai($siswa)
            ->getJson(route('api.jurnal.index'))
            ->assertJsonPath('jadwal.0.cetak_url', route('api.jurnal.cetak', ['jadwal_id' => $jadwal->id]));

        // Lembar cetak bukan pengecualian: tanpa token ia tetap tertutup.
        $this->withoutToken()->get(route('api.jurnal.cetak'))->assertUnauthorized();
    }

    public function test_unrecognised_barcode_is_rejected_before_contacting_absensi(): void
    {
        $this->setelAbsensi();
        [$siswa] = $this->siswaDiKelas();
        Http::fake();

        $this->sebagai($siswa)
            ->postJson(route('api.absen.store'), ['token' => 'https://situs-lain.test/apa-saja'])
            ->assertStatus(422)
            ->assertJsonPath('kode', 'bukan_barcode_absensi');

        Http::assertNothingSent();
    }

    public function test_scanned_barcode_is_forwarded_to_absensi_which_records_it(): void
    {
        $this->setelAbsensi();
        [$siswa] = $this->siswaDiKelas();
        Http::fake(['*' => Http::response(['ok' => true, 'jenis' => 'masuk', 'jam' => '06:55:00', 'terlambat' => false])]);

        $this->sebagai($siswa)
            ->postJson(route('api.absen.store'), ['token' => self::TOKEN_BARCODE])
            ->assertOk()
            ->assertJson(['ok' => true, 'tercatat' => true, 'jenis' => 'masuk'])
            ->assertJsonPath('pesan', 'Absen masuk tercatat pukul 06:55. Selamat belajar!');

        // Kunci API tetap di server; yang dikirim aplikasi hanyalah tokennya.
        Http::assertSent(fn (Request $permintaan) => $permintaan->method() === 'POST'
            && str_starts_with($permintaan->url(), 'http://absensi.test/presensi/barcode_api.php')
            && $permintaan['aksi'] === 'absen'
            && $permintaan['token'] === self::TOKEN_BARCODE
            && $permintaan['nomor_induk'] === $siswa->kunciAbsensi()
            && $permintaan['kunci'] === 'kunci-rahasia');
    }

    public function test_repeat_scan_within_the_gap_is_reported_as_not_recorded(): void
    {
        $this->setelAbsensi();
        [$siswa] = $this->siswaDiKelas();
        Http::fake(['*' => Http::response(['ok' => true, 'jenis' => 'ulang', 'tap_terakhir' => '06:55:00', 'bisa_lagi' => '07:05:00'])]);

        $this->sebagai($siswa)
            ->postJson(route('api.absen.store'), ['token' => self::TOKEN_BARCODE])
            ->assertOk()
            ->assertJson(['ok' => true, 'tercatat' => false])
            ->assertJsonPath('pesan', 'Absen kamu pukul 06:55 sudah tercatat, jadi pindaian ini belum dihitung. Pindai lagi mulai pukul 07:05 untuk absen berikutnya.');
    }

    public function test_monthly_attendance_counts_the_same_as_the_absensi_app(): void
    {
        $this->travelTo('2026-09-09 15:00');
        [$siswa] = $this->siswaDiKelas();
        $this->jadwalSekolahSeninJumat();
        $this->catatAbsensi($siswa, '2026-09-07', 0, '06:55:00');
        $this->catatAbsensi($siswa, '2026-09-08', 0, '07:30:00');
        $this->catatAbsensi($siswa, '2026-09-09', 2, null, 'Demam');

        $balasan = $this->sebagai($siswa)->getJson(route('api.absensi', ['bulan' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('bulan', '2026-09');

        $this->assertSame(1, $balasan->json('rekap.hadir'));
        $this->assertSame(1, $balasan->json('rekap.terlambat'));
        $this->assertSame(1, $balasan->json('rekap.sakit'));
    }

    public function test_book_catalogue_lists_school_copies_first_and_can_be_searched(): void
    {
        Storage::fake('kurikulum');
        Storage::disk('kurikulum')->put('buku-digital/file/informatika.pdf', '%PDF-1.4 isi buku');

        BukuDigital::factory()->diunduh('buku-digital/file/informatika.pdf')->create([
            'judul' => 'Informatika untuk SMP Kelas VII',
            'mata_pelajaran' => 'Informatika',
        ]);
        BukuDigital::factory()->create([
            'judul' => 'Matematika untuk SMP Kelas VII',
            'mata_pelajaran' => 'Matematika',
        ]);

        $siswa = Siswa::factory()->create();

        // Buku yang berkasnya sudah di sekolah tampil lebih dulu, dan hanya ia
        // yang punya alamat baca lewat portal — yang lain tetap ke SIBI.
        $balasan = $this->sebagai($siswa)->getJson(route('api.buku.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.judul', 'Informatika untuk SMP Kelas VII')
            ->assertJsonPath('data.0.tersedia_offline', true)
            ->assertJsonPath('data.0.baca_url', route('api.berkas.buku', 1))
            ->assertJsonPath('data.1.tersedia_offline', false)
            ->assertJsonPath('data.1.baca_url', null)
            ->assertJsonPath('data.1.url_sibi', 'https://buku.kemendikdasmen.go.id/file/contoh.pdf');

        // Pilihan penyaring datang dari isi katalog, bukan daftar tetap di APK.
        $this->assertSame(['Informatika', 'Matematika'], $balasan->json('pilihan.mata_pelajaran'));
        $this->assertSame(2, $balasan->json('ringkasan.total'));
        $this->assertSame(1, $balasan->json('ringkasan.lokal'));

        $this->sebagai($siswa)->getJson(route('api.buku.index', ['q' => 'Matematika']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Matematika untuk SMP Kelas VII');

        $this->sebagai($siswa)->getJson(route('api.buku.index', ['mata_pelajaran' => 'Informatika']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Informatika untuk SMP Kelas VII');
    }

    public function test_book_detail_carries_its_table_of_contents(): void
    {
        $buku = BukuDigital::factory()->create(['judul' => 'Informatika VII', 'mata_pelajaran' => 'Informatika']);
        $lain = BukuDigital::factory()->create(['judul' => 'Informatika VIII', 'mata_pelajaran' => 'Informatika']);

        $bab = $this->babBuku(['buku_digital_id' => $buku->id, 'level' => 1, 'urutan' => 1,
            'nomor_bab' => 'Bab 1', 'judul_bab' => 'Berpikir Komputasional', 'halaman' => 7]);
        $this->babBuku(['buku_digital_id' => $buku->id, 'induk_id' => $bab, 'level' => 2, 'urutan' => 2,
            'judul_bab' => 'Algoritma Sederhana', 'halaman' => 9]);
        // Daftar isi buku lain tidak boleh ikut terbawa.
        $this->babBuku(['buku_digital_id' => $lain->id, 'level' => 1, 'urutan' => 1,
            'judul_bab' => 'Isi Buku Lain']);

        $this->sebagai(Siswa::factory()->create())
            ->getJson(route('api.buku.show', $buku))
            ->assertOk()
            ->assertJsonPath('data.judul', 'Informatika VII')
            ->assertJsonCount(1, 'data.bab')
            ->assertJsonPath('data.bab.0.judul', 'Berpikir Komputasional')
            ->assertJsonPath('data.bab.0.sub.0.judul', 'Algoritma Sederhana')
            ->assertJsonPath('serupa.0.judul', 'Informatika VIII')
            ->assertDontSee('Isi Buku Lain');
    }

    public function test_book_file_needs_a_token_and_only_serves_pdf(): void
    {
        Storage::fake('kurikulum');
        Storage::disk('kurikulum')->put('buku-digital/file/informatika.pdf', '%PDF-1.4 isi buku');
        Storage::disk('kurikulum')->put('buku-digital/cover/informatika.png', 'PNG');

        $buku = BukuDigital::factory()->diunduh('buku-digital/file/informatika.pdf')
            ->create(['cover_path' => 'buku-digital/cover/informatika.png']);
        $audio = BukuDigital::factory()->diunduh('buku-digital/file/audio.mp3')->create(['tipe' => 'audio']);

        $siswa = Siswa::factory()->create();

        $this->sebagai($siswa)->get(route('api.berkas.buku', $buku))->assertOk();
        $this->sebagai($siswa)->get(route('api.berkas.buku-cover', $buku))->assertOk();

        // Hanya PDF yang disalurkan portal; sisanya tetap dibuka di SIBI.
        $this->sebagai($siswa)->get(route('api.berkas.buku', $audio))->assertNotFound();

        // Katalog memang milik semua siswa, tetapi tetap di belakang token.
        $this->withoutToken()->getJson(route('api.buku.index'))->assertUnauthorized();
        $this->withoutToken()->get(route('api.berkas.buku', $buku))->assertUnauthorized();
    }

    public function test_dead_school_database_answers_with_json_instead_of_a_web_page(): void
    {
        Schema::connection('kesiswaan')->drop('prestasi');

        $this->sebagai(Siswa::factory()->create())
            ->getJson(route('api.prestasi'))
            ->assertStatus(503)
            ->assertJsonPath('aplikasi', 'Kesiswaan');
    }

    /**
     * Satu baris daftar isi buku, ditulis langsung ke database Kurikulum.
     *
     * Portal hanya membaca katalog buku — menyinkron dan mengunduhnya tetap
     * urusan aplikasi Kurikulum — jadi modelnya sengaja tidak punya jalan
     * menulis yang bisa dipakai di sini.
     *
     * @param  array<string, mixed>  $baris
     */
    public function test_agenda_names_the_teaching_module_the_teacher_chose(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();

        $modul = ModulAjar::factory()->create(['judul' => 'Jaringan Dasar', 'pertemuan_ke' => 3]);

        AgendaMengajar::factory()->create([
            'kelas_id' => $rombel->id,
            'modul_ajar_id' => $modul->id,
            'waktu_pengisian' => now()->subDay(),
        ]);

        // Memilih modul tidak wajib di aplikasi Kurikulum, jadi agenda tanpa
        // modul adalah keadaan yang lazim - bukan data yang hilang.
        AgendaMengajar::factory()->create([
            'kelas_id' => $rombel->id,
            'modul_ajar_id' => null,
            'waktu_pengisian' => now()->subDays(2),
        ]);

        $balasan = $this->sebagai($siswa)->getJson(route('api.agenda'))->assertOk();

        $balasan->assertJsonPath('data.0.modul.id', $modul->id);
        $balasan->assertJsonPath('data.0.modul.judul', 'Jaringan Dasar');
        $balasan->assertJsonPath('data.0.modul.pertemuan_ke', 3);
        $balasan->assertJsonPath('data.1.modul', null);
    }

    public function test_a_module_carries_the_lesson_goals_but_never_the_assessments(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();

        $modul = ModulAjar::factory()->create(['judul' => 'Jaringan Dasar']);
        AgendaMengajar::factory()->create(['kelas_id' => $rombel->id, 'modul_ajar_id' => $modul->id]);

        $balasan = $this->sebagai($siswa)
            ->getJson(route('api.modul-ajar', $modul))
            ->assertOk()
            ->assertJsonPath('data.judul', 'Jaringan Dasar');

        $kunci = array_column($balasan->json('data.bagian'), 'kunci');

        // Tujuan pembelajaran didahulukan dengan sengaja: itu satu-satunya
        // bagian yang menjawab pertanyaan yang benar-benar dimiliki siswa.
        $this->assertSame('tujuan_pembelajaran', $kunci[0]);
        $this->assertContains('capaian_pembelajaran', $kunci);
        $this->assertContains('lkpd_petunjuk', $kunci);

        // Bagian kosong tidak ikut dikirim; modul Generator kerap hanya
        // mengisi separuh bagiannya.
        $this->assertNotContains('penutup', $kunci);

        foreach (ModulAjar::BAGIAN_DITAHAN as $ditahan) {
            $this->assertNotContains($ditahan, $kunci);
        }

        // Dan isinya tidak boleh menyelip lewat jalan lain mana pun: di sinilah
        // guru menuliskan kisi-kisi, rubrik, dan kerap kunci jawabannya.
        $balasan->assertDontSee('Kunci: B', escape: false);
        $balasan->assertDontSee('Rubrik', escape: false);
        $balasan->assertDontSee('Pretest', escape: false);
    }

    public function test_a_module_outside_the_students_own_agenda_does_not_exist_for_them(): void
    {
        [$siswa, $rombel] = $this->siswaDiKelas();

        $milikKelasLain = ModulAjar::factory()->create();
        AgendaMengajar::factory()->create([
            'kelas_id' => Rombel::factory()->create()->id,
            'modul_ajar_id' => $milikKelasLain->id,
        ]);

        // Agenda yang ditolak admin tidak pernah tampil di daftar agenda, jadi
        // modulnya pun tidak boleh bisa dibuka lewat nomornya.
        $dariAgendaDitolak = ModulAjar::factory()->create();
        AgendaMengajar::factory()->ditolak()->create([
            'kelas_id' => $rombel->id,
            'modul_ajar_id' => $dariAgendaDitolak->id,
        ]);

        $belumDipakai = ModulAjar::factory()->create();

        // 404, bukan 403: "Anda tidak berhak" tetap memberi tahu bahwa modul
        // bernomor itu ada, dan nomor bisa dicoba satu per satu.
        foreach ([$milikKelasLain, $dariAgendaDitolak, $belumDipakai] as $modul) {
            $this->sebagai($siswa)->getJson(route('api.modul-ajar', $modul))->assertNotFound();
            $this->sebagai($siswa)->getJson(route('api.berkas.modul-ajar', $modul))->assertNotFound();
        }
    }

    public function test_an_uploaded_module_is_served_through_the_portal(): void
    {
        Storage::fake('kurikulum');
        Storage::disk('kurikulum')->put('modul-ajar/modul.pdf', '%PDF-1.4 contoh');

        [$siswa, $rombel] = $this->siswaDiKelas();

        $modul = ModulAjar::factory()->unggahan('modul-ajar/modul.pdf')->create();
        AgendaMengajar::factory()->create(['kelas_id' => $rombel->id, 'modul_ajar_id' => $modul->id]);

        // Modul unggahan tidak punya bagian terisi sama sekali - berkasnya yang
        // menjadi isinya, dan tanpa itu menu ini terasa rusak di sekolah yang
        // gurunya terbiasa mengunggah.
        $this->sebagai($siswa)->getJson(route('api.modul-ajar', $modul))
            ->assertOk()
            ->assertJsonCount(0, 'data.bagian')
            ->assertJsonPath('data.berkas_nama', 'modul.pdf')
            ->assertJsonPath('data.berkas_ukuran', '1,5 MB');

        $this->sebagai($siswa)->get(route('api.berkas.modul-ajar', $modul))->assertOk();

        $this->withoutToken()->get(route('api.berkas.modul-ajar', $modul))->assertUnauthorized();
    }

    private function babBuku(array $baris): int
    {
        return DB::connection('kurikulum')->table('buku_digital_bab')->insertGetId($baris);
    }

    /** Permintaan dengan token bearer milik siswa tersebut. */
    private function sebagai(Siswa $siswa): self
    {
        $mentah = SiswaApiToken::acak();

        SiswaApiToken::create([
            'siswa_id' => $siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        return $this->withToken($mentah);
    }

    private function setelAbsensi(): void
    {
        config([
            'services.absensi.url' => 'http://absensi.test/presensi',
            'services.absensi.barcode_key' => 'kunci-rahasia',
        ]);
    }
}
