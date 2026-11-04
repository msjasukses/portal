<?php

namespace Tests\Feature\Support;

use App\Models\Absensi\JadwalMagang;
use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use App\Support\RekapAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * Aturan status harian harus sama persis dengan aplikasi Absensi.
 * Minggu uji: Senin 7 September – Minggu 13 September 2026.
 */
class RekapAbsensiTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    /**
     * @return array<string, string> tanggal => status
     */
    private function statusPerTanggal(Siswa $siswa, ?int $rombelId = null): array
    {
        $laporan = app(RekapAbsensi::class)->laporan(
            $siswa,
            $rombelId,
            CarbonImmutable::parse('2026-09-07'),
            CarbonImmutable::parse('2026-09-13'),
        );

        return collect($laporan['rows'])->mapWithKeys(fn (array $baris) => [$baris['tanggal']->toDateString() => $baris['status']])->all();
    }

    public function test_school_days_follow_check_in_time_and_recorded_absences(): void
    {
        $this->travelTo('2026-09-14');
        $this->jadwalSekolahSeninJumat();
        $siswa = Siswa::factory()->create();
        $this->catatAbsensi($siswa, '2026-09-07', 0, '06:50:00');
        $this->catatAbsensi($siswa, '2026-09-08', 0, '07:15:00');
        $this->catatAbsensi($siswa, '2026-09-09', 2);
        $this->catatAbsensi($siswa, '2026-09-11', 3, null, 'Acara keluarga');

        $this->assertSame([
            '2026-09-07' => 'hadir',
            '2026-09-08' => 'terlambat',
            '2026-09-09' => 'sakit',
            '2026-09-10' => 'alpha',
            '2026-09-11' => 'izin',
            '2026-09-12' => 'libur',
            '2026-09-13' => 'libur',
        ], $this->statusPerTanggal($siswa));
    }

    public function test_internship_days_count_as_magang_except_on_national_holidays(): void
    {
        $this->travelTo('2026-09-14');
        $this->jadwalSekolahSeninJumat();
        $siswa = Siswa::factory()->create();
        JadwalMagang::factory()->untuk($siswa)->create([
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-30',
            'hari' => '1,2,3,4,5,6',
            'tempat' => 'PT Maju Jaya',
        ]);
        DB::connection('absensi')->table('hari_libur')->insert([
            'tanggal' => '2026-09-09', 'keterangan' => 'Libur Nasional', 'jenis' => 'nasional',
        ]);
        $this->catatAbsensi($siswa, '2026-09-08', 0, '06:40:00');
        $this->catatAbsensi($siswa, '2026-09-10', 4);
        $this->catatAbsensi($siswa, '2026-09-11', 3);

        $laporan = app(RekapAbsensi::class)->laporan($siswa, null, CarbonImmutable::parse('2026-09-07'), CarbonImmutable::parse('2026-09-13'));

        $this->assertSame([
            '2026-09-07' => 'magang',
            '2026-09-08' => 'hadir',
            '2026-09-09' => 'libur',
            '2026-09-10' => 'alpha',
            '2026-09-11' => 'izin',
            '2026-09-12' => 'magang',
            '2026-09-13' => 'libur',
        ], $this->statusPerTanggal($siswa));
        $this->assertSame('Magang di PT Maju Jaya', $laporan['rows'][0]['keterangan']);
        $this->assertSame('Libur Nasional', $laporan['rows'][2]['keterangan']);
        $this->assertSame(['hadir' => 1, 'izin' => 1, 'magang' => 2, 'alpha' => 1, 'libur' => 2], array_filter($laporan['rekap']));
    }

    public function test_class_shift_overrides_the_general_late_limit(): void
    {
        $this->travelTo('2026-09-14');
        $this->jadwalSekolahSeninJumat();
        $siswa = Siswa::factory()->create();
        $shiftId = DB::connection('absensi')->table('shift')->insertGetId([
            'nama' => 'Siang', 'jam_masuk' => '12:00:00', 'batas_terlambat' => '12:30:00', 'jam_pulang' => '17:00:00',
        ]);
        DB::connection('absensi')->table('jadwal_shift_kelas')->insert(['rombel_id' => 9, 'hari' => 1, 'shift_id' => $shiftId]);
        $this->catatAbsensi($siswa, '2026-09-07', 0, '12:10:00');

        $this->assertSame('hadir', $this->statusPerTanggal($siswa, 9)['2026-09-07']);
        $this->assertSame('terlambat', $this->statusPerTanggal($siswa, null)['2026-09-07']);
    }

    public function test_student_without_nis_is_matched_by_nisn(): void
    {
        $this->travelTo('2026-09-14');
        $this->jadwalSekolahSeninJumat();
        $siswa = Siswa::factory()->tanpaNis()->create();
        DB::connection('absensi')->table('absensi_siswa')->insert([
            'nis' => $siswa->nisn, 'tanggal' => '2026-09-07', 'jam' => '06:45:00', 'status' => 0,
        ]);

        $this->assertSame('hadir', $this->statusPerTanggal($siswa)['2026-09-07']);
    }

    public function test_month_period_stops_at_today_and_school_year_start(): void
    {
        $this->travelTo('2026-07-20');
        $tahunAjaran = TahunAjaran::factory()->create(['tanggal_mulai' => '2026-07-13', 'tanggal_selesai' => '2027-06-30']);

        [$dari, $sampai] = RekapAbsensi::periodeBulan(today(), $tahunAjaran);

        $this->assertSame('2026-07-13', $dari->toDateString());
        $this->assertSame('2026-07-20', $sampai->toDateString());
    }
}
