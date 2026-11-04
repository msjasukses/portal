<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * Menu Absen Barcode: portal meneruskan token hasil pindaian ke barcode_api.php
 * aplikasi Absensi, dan aplikasi itu yang menulis absensinya.
 */
class AbsenBarcodeControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    private const TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.absensi.url' => 'http://absensi.test/presensi',
            'services.absensi.barcode_key' => 'kunci-rahasia',
        ]);
    }

    public function test_shows_scan_page_with_today_attendance(): void
    {
        $this->travelTo('2026-10-27 07:10');
        [$siswa] = $this->siswaDiKelas();
        $this->jadwalSekolahSeninJumat();
        $this->catatAbsensi($siswa, '2026-10-27', 0, '06:55:00');
        Http::fake();

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absen'))
            ->assertOk()
            ->assertViewHas('aktif', true)
            ->assertViewHas('token', null)
            ->assertSeeInOrder([
                'Absen dengan Barcode', 'Absen Sekarang', 'Absensi Hari Ini', 'Hadir', '06:55',
                // Sudah absen masuk, belum pulang.
                'Pindaian Berikutnya', 'absen pulang',
            ]);

        Http::assertNothingSent();
    }

    public function test_scanned_token_is_verified_before_the_confirm_button_is_shown(): void
    {
        [$siswa] = $this->siswaDiKelas();
        Http::fake(['*' => Http::response(['ok' => true, 'sisa_detik' => 97])]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absen', ['token' => 'http://portal.test/siswa/absen?token='.self::TOKEN]))
            ->assertOk()
            ->assertViewHas('token', self::TOKEN)
            ->assertSee('value="'.self::TOKEN.'"', false);

        Http::assertSent(fn (Request $permintaan) => $permintaan->method() === 'GET'
            && str_starts_with($permintaan->url(), 'http://absensi.test/presensi/barcode_api.php')
            && $permintaan['aksi'] === 'verifikasi'
            && $permintaan['token'] === self::TOKEN
            && $permintaan['kunci'] === 'kunci-rahasia');
    }

    public function test_expired_token_is_reported_without_a_confirm_button(): void
    {
        [$siswa] = $this->siswaDiKelas();
        Http::fake(['*' => Http::response(['ok' => false, 'kode' => 'kedaluwarsa', 'pesan' => 'Barcode sudah berganti.'])]);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absen', ['token' => self::TOKEN]))
            ->assertOk()
            ->assertSee('Barcode sudah berganti.')
            // Tombol kirim hanya ada bila token masih berlaku.
            ->assertDontSee('name="token" value="'.self::TOKEN.'"', false);
    }

    public function test_records_attendance_through_the_absensi_app(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '12345']);
        Http::fake(['*' => Http::response([
            'ok' => true, 'jenis' => 'masuk', 'jam' => '07:12:30', 'terlambat' => false,
        ])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertRedirect(route('siswa.absen'))
            ->assertSessionHas('success', 'Absen masuk tercatat pukul 07:12. Selamat belajar!');

        Http::assertSent(fn (Request $permintaan) => $permintaan->method() === 'POST'
            && $permintaan['aksi'] === 'absen'
            && $permintaan['tipe'] === 'siswa'
            && $permintaan['nomor_induk'] === '12345'
            && $permintaan['token'] === self::TOKEN);
    }

    public function test_late_entry_is_recorded_but_said_so(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response(['ok' => true, 'jenis' => 'masuk', 'jam' => '07:45:00', 'terlambat' => true])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionHas('success', 'Absen masuk tercatat pukul 07:45, tetapi melewati batas jam masuk (terlambat).');
    }

    /**
     * Pindaian dalam jeda tap aplikasi Absensi tidak ditulis ulang. Bukan
     * kegagalan, tetapi juga bukan absen yang tercatat — siswa perlu tahu kapan
     * absen berikutnya (pulang) bisa dipindai.
     */
    public function test_repeated_scan_says_when_the_next_scan_will_count(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response([
            'ok' => true, 'jenis' => 'ulang', 'jam' => '07:46:00',
            'tap_terakhir' => '07:41:25', 'bisa_lagi' => '07:51:25', 'jeda_menit' => 10,
        ])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionMissing('success')
            ->assertSessionHas('warning', 'Absen kamu pukul 07:41 sudah tercatat, jadi pindaian ini belum dihitung. Pindai lagi mulai pukul 07:51 untuk absen berikutnya.');
    }

    /** Aplikasi Absensi versi lama tidak mengirim jendela jedanya. */
    public function test_repeated_scan_without_window_still_explains_itself(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response(['ok' => true, 'jenis' => 'ulang', 'jam' => '07:46:00'])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionHas('warning', 'Kamu baru saja absen, jadi pindaian ini belum dihitung. Tunggu beberapa menit sebelum memindai lagi.');
    }

    public function test_leaving_is_recorded_as_pulang(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response([
            'ok' => true, 'jenis' => 'pulang', 'jam' => '14:05:00', 'jam_masuk' => '06:55:00',
        ])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionHas('success', 'Absen pulang tercatat pukul 14:05. Hati-hati di jalan!');
    }

    public function test_rejected_token_is_shown_as_an_error(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response(['ok' => false, 'kode' => 'kedaluwarsa', 'pesan' => 'Barcode sudah berganti.'])]);

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertRedirect(route('siswa.absen'))
            ->assertSessionHas('error', 'Barcode sudah berganti.');
    }

    public function test_scan_that_is_not_an_attendance_barcode_never_reaches_the_absensi_app(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake();

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => 'https://contoh.test/promo'])
            ->assertRedirect(route('siswa.absen'))
            ->assertSessionHas('error', 'Barcode tidak dikenali. Pindai barcode pada layar absensi sekolah.');

        Http::assertNothingSent();
    }

    public function test_unreachable_absensi_app_is_reported_as_a_connection_problem(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(fn () => throw new ConnectionException('tidak terhubung'));

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionHas('error', 'Aplikasi Absensi tidak bisa dihubungi. Coba lagi sebentar.');
    }

    public function test_menu_is_off_and_nothing_is_sent_when_the_key_is_not_configured(): void
    {
        config(['services.absensi.barcode_key' => '']);
        [$siswa] = $this->siswaDiKelas();
        Http::fake();

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.absen'))
            ->assertOk()
            ->assertViewHas('aktif', false)
            ->assertSee('Absen barcode belum aktif.');

        $this->actingAs($siswa, 'siswa')
            ->post(route('siswa.absen.store'), ['token' => self::TOKEN])
            ->assertSessionHas('error', 'Absen barcode belum disetel di portal. Hubungi admin sekolah.');

        Http::assertNothingSent();
    }

    public function test_guest_scanning_the_barcode_is_sent_to_login_and_back(): void
    {
        $siswa = Siswa::factory()->create();
        Http::fake(['*' => Http::response(['ok' => true, 'sisa_detik' => 60])]);

        $this->get(route('siswa.absen', ['token' => self::TOKEN]))->assertRedirect(route('siswa.login'));

        $this->post(route('siswa.login'), ['nisn' => $siswa->nisn, 'password' => 'password'])
            ->assertRedirect(route('siswa.absen', ['token' => self::TOKEN]));
    }
}
