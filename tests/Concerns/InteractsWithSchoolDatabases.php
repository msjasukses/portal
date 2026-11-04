<?php

namespace Tests\Concerns;

use App\Models\Datacenter\Rombel;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\SiswaRombel;
use App\Models\Datacenter\TahunAjaran;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel aplikasi Data Center, Absensi, Kurikulum, dan Kesiswaan (hanya kolom
 * yang dipakai Portal Siswa) di database SQLite in-memory masing-masing.
 * Strukturnya mengikuti database asli aplikasi tersebut. Dipanggil otomatis
 * oleh Laravel sebelum setiap test di kelas yang memakai trait ini.
 */
trait InteractsWithSchoolDatabases
{
    protected function setUpInteractsWithSchoolDatabases(): void
    {
        $this->buatSkemaDatacenter();
        $this->buatSkemaAbsensi();
        $this->buatSkemaKurikulum();
        $this->buatSkemaKesiswaan();
    }

    /**
     * Siswa yang ditempatkan di satu kelas pada tahun ajaran aktif.
     *
     * @param  array<string, mixed>  $atribut
     * @return array{0: Siswa, 1: Rombel}
     */
    protected function siswaDiKelas(array $atribut = []): array
    {
        $tahunAjaran = TahunAjaran::where('is_aktif', true)->first() ?? TahunAjaran::factory()->aktif()->create();
        $rombel = Rombel::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->create($atribut);

        SiswaRombel::factory()->create([
            'siswa_id' => $siswa->id,
            'rombongan_belajar_id' => $rombel->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        return [$siswa, $rombel];
    }

    /** Jadwal sekolah siswa di aplikasi Absensi: Senin–Jumat masuk 06:30, batas 07:00; Sabtu–Minggu libur. */
    protected function jadwalSekolahSeninJumat(): void
    {
        foreach (range(1, 7) as $hari) {
            DB::connection('absensi')->table('jadwal_absensi')->insert([
                'tipe' => 'siswa',
                'hari' => $hari,
                'jam_masuk' => $hari <= 5 ? '06:30:00' : null,
                'batas_terlambat' => $hari <= 5 ? '07:00:00' : null,
                'jam_pulang' => $hari <= 5 ? '14:00:00' : null,
                'libur' => $hari <= 5 ? 0 : 1,
            ]);
        }
    }

    /** Catatan absensi_siswa: kode 0=masuk, 1=pulang, 2=sakit, 3=izin, 4=alpha. */
    protected function catatAbsensi(Siswa $siswa, string $tanggal, int $kode, ?string $jam = null, ?string $keterangan = null): void
    {
        DB::connection('absensi')->table('absensi_siswa')->insert([
            'nis' => $siswa->kunciAbsensi(),
            'tanggal' => $tanggal,
            'jam' => $jam,
            'status' => $kode,
            'keterangan' => $keterangan,
        ]);
    }

    private function buatSkemaDatacenter(): void
    {
        $schema = Schema::connection('datacenter');

        $schema->create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode_tahun_ajaran');
            $table->string('nama_tahun_ajaran');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
        });

        $schema->create('guru', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->nullable();
            $table->string('nama_ptk');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama_mapel');
            $table->timestamps();
        });

        $schema->create('rombongan_belajar', function (Blueprint $table) {
            $table->id();
            $table->string('nama_rombel');
            $table->unsignedTinyInteger('tingkat');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->unsignedBigInteger('wali_kelas_id')->nullable();
            $table->timestamps();
        });

        $schema->create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nisn')->unique();
            $table->string('nis')->nullable();
            $table->string('nama_siswa');
            $table->string('jenis_kelamin')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('status_siswa')->default('Aktif');
            $table->string('remember_token')->nullable();
            $table->string('account_status')->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

        $schema->create('siswa_rombel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('rombongan_belajar_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->timestamps();
        });
    }

    private function buatSkemaAbsensi(): void
    {
        $schema = Schema::connection('absensi');

        $schema->create('absensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nis');
            $table->date('tanggal');
            $table->time('jam')->nullable();
            $table->unsignedTinyInteger('status');
            $table->string('keterangan')->nullable();
        });

        $schema->create('jadwal_absensi', function (Blueprint $table) {
            $table->id();
            $table->string('tipe');
            $table->unsignedTinyInteger('hari');
            $table->time('jam_masuk')->nullable();
            $table->time('batas_terlambat')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->boolean('libur')->default(false);
        });

        $schema->create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->date('tanggal_selesai')->nullable();
            $table->string('keterangan');
            $table->string('jenis')->default('sekolah');
        });

        $schema->create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->time('jam_masuk');
            $table->time('batas_terlambat');
            $table->time('jam_pulang');
        });

        $schema->create('jadwal_shift_kelas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rombel_id');
            $table->unsignedTinyInteger('hari');
            $table->unsignedBigInteger('shift_id');
        });

        $schema->create('jadwal_magang', function (Blueprint $table) {
            $table->id();
            $table->string('nis');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('hari')->default('1,2,3,4,5');
            $table->string('tempat');
            $table->string('pembimbing')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamp('dibuat')->nullable();
        });
    }

    private function buatSkemaKurikulum(): void
    {
        $schema = Schema::connection('kurikulum');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $schema->create('identitas_sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sekolah')->nullable();
            $table->timestamps();
        });

        $schema->create('tugas', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable();
            $table->unsignedBigInteger('kelas_id')->nullable();
            $table->unsignedBigInteger('pegawai_id')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('file_lampiran')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();
        });

        $schema->create('pengumpulan_tugas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tugas_id');
            $table->unsignedBigInteger('siswa_id');
            $table->text('catatan')->nullable();
            $table->string('file')->nullable();
            $table->timestamp('dikumpulkan_pada')->nullable();
            $table->boolean('terlambat')->default(false);
            $table->unsignedTinyInteger('nilai')->nullable();
            $table->text('umpan_balik')->nullable();
            $table->timestamp('dinilai_pada')->nullable();
            $table->timestamps();
        });

        $schema->create('materi_online', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable();
            $table->unsignedBigInteger('kelas_id')->nullable();
            $table->unsignedBigInteger('pegawai_id')->nullable();
            $table->string('file')->nullable();
            $table->string('link_video')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        $schema->create('agenda_mengajar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_id');
            $table->string('mengajar_sebagai')->default('normal');
            $table->dateTime('waktu_pengisian');
            $table->string('jam_ke')->nullable();
            $table->unsignedTinyInteger('jumlah_jam')->default(1);
            $table->unsignedBigInteger('kelas_id');
            $table->unsignedBigInteger('mata_pelajaran_id');
            $table->json('kehadiran_siswa')->nullable();
            $table->text('materi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('Sedang Ditinjau');
            $table->string('tahun_ajaran')->nullable();
            $table->timestamps();
        });

        $schema->create('jurnal_pkl', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('jadwal_magang_id');
            $table->string('tempat');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->text('kegiatan');
            $table->text('hasil')->nullable();
            $table->text('kendala')->nullable();
            $table->string('foto')->nullable();
            $table->string('status')->default('Menunggu');
            $table->text('catatan_pembimbing')->nullable();
            $table->unsignedBigInteger('diperiksa_oleh')->nullable();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->timestamps();
        });
    }

    private function buatSkemaKesiswaan(): void
    {
        $schema = Schema::connection('kesiswaan');

        $schema->create('jenis_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->timestamps();
        });

        $schema->create('data_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jenis_pelanggaran_id');
            $table->string('nama');
            $table->unsignedInteger('poin')->default(0);
            $table->timestamps();
        });

        $schema->create('pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('data_pelanggaran_id');
            $table->date('tanggal');
            $table->unsignedInteger('poin')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('tindak_lanjut')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        $schema->create('prestasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->string('nama_prestasi');
            $table->string('bidang')->default('Akademik');
            $table->string('tingkat')->default('Sekolah');
            $table->string('peringkat')->nullable();
            $table->string('penyelenggara')->nullable();
            $table->date('tanggal')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
}
