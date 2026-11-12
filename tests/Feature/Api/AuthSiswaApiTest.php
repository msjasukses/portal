<?php

namespace Tests\Feature\Api;

use App\Models\Datacenter\Siswa;
use App\Models\SiswaApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

/**
 * Pintu masuk API aplikasi ponsel: penanda alamat, peta modul, dan daur hidup
 * token. Aturan akunnya harus sama ketatnya dengan halaman masuk di peramban —
 * jalan masuk yang lebih longgar sama saja dengan tidak ada aturan.
 */
class AuthSiswaApiTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_ping_identifies_this_address_as_the_portal(): void
    {
        $this->getJson(route('api.ping'))
            ->assertOk()
            ->assertJson(['ok' => true, 'aplikasi' => 'Portal Siswa', 'versi_api' => 1]);
    }

    public function test_arahan_tells_the_app_where_the_exam_app_lives(): void
    {
        config([
            'portal.ujian_url' => 'https://sekolah.test/cbt/',
            'services.absensi.url' => 'http://absensi.test/presensi',
            'services.absensi.barcode_key' => 'kunci-rahasia',
        ]);

        $balasan = $this->getJson(route('api.arahan'))->assertOk();

        // Garis miring di ujung dibuang: aplikasi menyambung jalur di belakangnya.
        $balasan->assertJsonPath('ujian_url', 'https://sekolah.test/cbt');
        $this->assertContains('absen', $this->kunciMenu($balasan->json('menu')));
    }

    public function test_arahan_hides_the_exam_and_barcode_menus_when_they_are_not_set_up(): void
    {
        config([
            'portal.ujian_url' => null,
            'services.absensi.barcode_key' => '',
        ]);

        $balasan = $this->getJson(route('api.arahan'))->assertOk();

        $balasan->assertJsonPath('ujian_url', null);
        $this->assertNotContains('absen', $this->kunciMenu($balasan->json('menu')));
    }

    public function test_login_returns_a_token_and_stores_only_its_hash(): void
    {
        $this->freezeSecond();
        $siswa = Siswa::factory()->create(['failed_login_count' => 3]);

        $balasan = $this->postJson(route('api.auth.login'), [
            'nisn' => $siswa->nisn,
            'password' => 'password',
            'perangkat' => 'Redmi Note 10',
        ])->assertOk()->assertJsonPath('siswa.nisn', (string) $siswa->nisn);

        $mentah = $balasan->json('token');
        $this->assertNotEmpty($mentah);

        $token = SiswaApiToken::sole();
        $this->assertSame(SiswaApiToken::sidik($mentah), $token->token);
        $this->assertNotSame($mentah, $token->token);
        $this->assertSame('Redmi Note 10', $token->perangkat);
        $this->assertTrue($token->kedaluwarsa_at->equalTo(now()->addDays(SiswaApiToken::UMUR_HARI)));

        // Penanda login di Data Center ikut diperbarui, sama dengan di peramban.
        $this->assertSame(0, (int) $siswa->refresh()->failed_login_count);
    }

    public function test_login_rejects_a_wrong_password_and_counts_it(): void
    {
        $siswa = Siswa::factory()->create();

        $this->postJson(route('api.auth.login'), ['nisn' => $siswa->nisn, 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nisn' => 'Kombinasi NISN dan kata sandi tidak cocok.']);

        $this->assertSame(1, (int) $siswa->refresh()->failed_login_count);
        $this->assertSame(0, SiswaApiToken::count());
    }

    public function test_login_rejects_locked_and_inactive_accounts(): void
    {
        $terkunci = Siswa::factory()->terkunci()->create();
        $nonaktif = Siswa::factory()->nonaktif()->create();

        $this->postJson(route('api.auth.login'), ['nisn' => $terkunci->nisn, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nisn' => 'Akun dikunci sementara karena terlalu banyak percobaan. Coba lagi dalam 10 menit.']);

        $this->postJson(route('api.auth.login'), ['nisn' => $nonaktif->nisn, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nisn' => 'Akun siswa tidak aktif. Hubungi admin Data Center.']);

        $this->assertSame(0, SiswaApiToken::count());
    }

    public function test_protected_endpoint_needs_a_token(): void
    {
        $this->getJson(route('api.auth.saya'))
            ->assertUnauthorized()
            ->assertJsonPath('pesan', 'Token tidak disertakan. Silakan masuk kembali.');

        $this->withToken('bukan-token-yang-pernah-diterbitkan')
            ->getJson(route('api.auth.saya'))
            ->assertUnauthorized()
            ->assertJsonPath('pesan', 'Sesi Anda sudah berakhir. Silakan masuk kembali.');
    }

    public function test_expired_token_is_rejected_and_thrown_away(): void
    {
        $siswa = Siswa::factory()->create();
        $mentah = $this->tokenUntuk($siswa, ['kedaluwarsa_at' => now()->subMinute()]);

        $this->withToken($mentah)->getJson(route('api.auth.saya'))->assertUnauthorized();

        $this->assertSame(0, SiswaApiToken::count());
    }

    public function test_token_of_a_deactivated_student_stops_working(): void
    {
        $siswa = Siswa::factory()->create();
        $mentah = $this->tokenUntuk($siswa);

        $this->withToken($mentah)->getJson(route('api.auth.saya'))->assertOk();

        $siswa->forceFill(['account_status' => 'suspended'])->save();

        $this->withToken($mentah)
            ->getJson(route('api.auth.saya'))
            ->assertUnauthorized()
            ->assertJsonPath('pesan', 'Akun siswa tidak aktif. Hubungi admin Data Center.');

        $this->assertSame(0, SiswaApiToken::count());
    }

    public function test_logout_removes_only_the_token_of_this_device(): void
    {
        $siswa = Siswa::factory()->create();
        $ponsel = $this->tokenUntuk($siswa, ['perangkat' => 'Ponsel']);
        $this->tokenUntuk($siswa, ['perangkat' => 'Tablet']);

        $this->withToken($ponsel)->postJson(route('api.auth.logout'))->assertOk();

        $this->assertSame('Tablet', SiswaApiToken::sole()->perangkat);
    }

    /**
     * @param  array<string, mixed>  $atribut
     * @return string token mentah
     */
    private function tokenUntuk(Siswa $siswa, array $atribut = []): string
    {
        $mentah = SiswaApiToken::acak();

        SiswaApiToken::create([
            'siswa_id' => $siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
            ...$atribut,
        ]);

        return $mentah;
    }

    /**
     * @param  list<array{kunci: string}>  $menu
     * @return list<string>
     */
    private function kunciMenu(array $menu): array
    {
        return array_column($menu, 'kunci');
    }
}
