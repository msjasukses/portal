<?php

namespace Tests\Feature\Api;

use App\Models\Datacenter\Siswa;
use App\Models\SiswaApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
    public function test_arahan_announces_the_latest_apk_release(): void
    {
        $this->manifesRilis([
            'kode_versi' => 7,
            'versi' => '1.2.0',
            'wajib_minimal' => 4,
            'terbit_pada' => '2026-11-20',
            'catatan' => 'Katalog buku digital.',
            'apk' => [
                'arm64-v8a' => [
                    'url' => 'rilis/cbt-siswa-1.2.0-arm64-v8a.apk',
                    'ukuran' => 27262976,
                    'sha256' => str_repeat('a', 64),
                ],
            ],
        ]);

        $balasan = $this->getJson(route('api.arahan'))->assertOk();

        $balasan->assertJsonPath('pembaruan.kode_versi', 7);
        $balasan->assertJsonPath('pembaruan.versi', '1.2.0');
        $balasan->assertJsonPath('pembaruan.wajib_minimal', 4);

        // Alamat relatif dilengkapi alamat peladen yang sedang menjawab. Inilah
        // yang membuat satu terkini.json yang sama dipakai di jaringan sekolah
        // dan di VPS tanpa disunting sebaris pun.
        $balasan->assertJsonPath(
            'pembaruan.apk.arm64-v8a.url',
            url('rilis/cbt-siswa-1.2.0-arm64-v8a.apk'),
        );
    }

    public function test_arahan_says_nothing_about_updates_before_the_first_release(): void
    {
        config(['portal.rilis_manifes' => storage_path('framework/testing/belum-ada-rilis.json')]);

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('pembaruan', null);
    }

    public function test_a_broken_release_manifest_never_breaks_the_discovery_endpoint(): void
    {
        // Endpoint ini dipanggil sebelum siswa masuk, jadi ia tidak boleh
        // pernah jatuh — apalagi karena satu tanda kutip yang salah ketik di
        // berkas rilis yang disunting tangan di server.
        $this->manifesMentah('{ "kode_versi": 7, "apk": ');

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('pembaruan', null);

        // Manifes yang JSON-nya utuh tetapi keterangan berkasnya separuh jadi
        // juga ditolak: tombol Perbarui yang menuju ke tempat yang tidak ada
        // dibaca siswa sebagai aplikasi yang rusak, dan itu lebih buruk
        // daripada pembaruan yang tidak pernah ditawarkan.
        $this->manifesRilis([
            'kode_versi' => 7,
            'apk' => [
                'arm64-v8a' => [
                    'url' => 'rilis/cbt-siswa-1.2.0-arm64-v8a.apk',
                    'ukuran' => 27262976,
                    'sha256' => 'sidik-jari-yang-terpotong-saat-ditempel',
                ],
            ],
        ]);

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('pembaruan', null);
    }

    public function test_a_release_manifest_saved_with_a_byte_order_mark_still_works(): void
    {
        // Notepad dan PowerShell di Windows menambahkan BOM tanpa diminta, dan
        // json_decode menolak berkas yang dimulai dengan tiga bita itu. Yang
        // membuatnya pantas diuji adalah bentuk kegagalannya: manifes dianggap
        // tidak ada, portal berkata "tidak ada pembaruan", dan tidak seorang
        // siswa pun menerima rilisnya tanpa satu pesan galat di mana pun.
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);

        $this->manifesMentah($bom.(string) json_encode([
            'kode_versi' => 7,
            'versi' => '1.2.0',
            'apk' => [
                'universal' => [
                    'url' => 'rilis/cbt-siswa-1.2.0.apk',
                    'ukuran' => 79930262,
                    'sha256' => str_repeat('c', 64),
                ],
            ],
        ]));

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('pembaruan.kode_versi', 7);
    }

    /**
     * @param  array<string, mixed>  $manifes
     */
    private function manifesRilis(array $manifes): void
    {
        $this->manifesMentah((string) json_encode($manifes));
    }

    private function manifesMentah(string $isi): void
    {
        File::ensureDirectoryExists(dirname($this->berkasManifesUji()));
        File::put($this->berkasManifesUji(), $isi);

        config(['portal.rilis_manifes' => $this->berkasManifesUji()]);
    }

    private function berkasManifesUji(): string
    {
        return storage_path('framework/testing/terkini-uji.json');
    }

    protected function tearDown(): void
    {
        File::delete($this->berkasManifesUji());

        parent::tearDown();
    }

    private function kunciMenu(array $menu): array
    {
        return array_column($menu, 'kunci');
    }

    // ---- Logo sekolah -------------------------------------------------------

    public function test_arahan_points_at_the_school_logo_the_data_center_holds(): void
    {
        Storage::fake('datacenter');
        $this->logoSekolah('sekolah/logo.jpg');

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('logo_url', url('api/v1/logo'));
    }

    public function test_arahan_says_there_is_no_logo_before_the_school_uploads_one(): void
    {
        Storage::fake('datacenter');

        DB::connection('datacenter')->table('sekolah')->insert([
            'nama_sekolah' => 'SMP Negeri 93',
            'logo' => null,
        ]);

        // Null, bukan alamat yang menjawab 404. Sekolah yang belum mengunggah
        // logo adalah keadaan yang sah, dan aplikasi memakai logo bawaannya.
        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('logo_url', null);
    }

    public function test_the_logo_is_served_without_a_token_because_it_greets_the_login_screen(): void
    {
        Storage::fake('datacenter');
        $this->logoSekolah('sekolah/logo.jpg');

        $balasan = $this->withoutToken()->get(route('api.logo'))->assertOk();

        $this->assertSame('image/jpeg', $balasan->headers->get('Content-Type'));
        $this->assertSame('logo-sekolah-ini', $balasan->streamedContent());
    }

    public function test_a_logo_recorded_but_gone_from_disk_is_treated_as_no_logo(): void
    {
        // Folder storage kerap tertinggal saat aplikasi dipindah antar server,
        // sehingga barisnya ada di database tetapi berkasnya tidak. Aplikasi
        // harus diberi tahu "tidak ada logo", bukan dikirimi alamat yang patah.
        Storage::fake('datacenter');
        $this->logoSekolah('sekolah/logo.jpg', berkasAda: false);

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('logo_url', null);

        $this->withoutToken()->get(route('api.logo'))->assertNotFound();
    }

    public function test_a_missing_datacenter_disk_never_breaks_the_discovery_endpoint(): void
    {
        // Terjadi ketika berkas config sudah diperbarui di server tetapi
        // config lamanya masih ter-cache: disk "datacenter" belum dikenal,
        // dan Storage::disk() atasnya melempar galat. Arahan dipanggil
        // sebelum siswa masuk, jadi ia harus tetap menjawab.
        DB::connection('datacenter')->table('sekolah')->insert([
            'nama_sekolah' => 'SMP Negeri 93',
            'logo' => 'sekolah/logo.jpg',
        ]);

        config(['filesystems.disks.datacenter' => null]);
        Storage::forgetDisk('datacenter');

        $this->getJson(route('api.arahan'))
            ->assertOk()
            ->assertJsonPath('logo_url', null);
    }

    /** Catat logo sekolah di Data Center, berikut berkasnya di disk. */
    private function logoSekolah(string $jalur, bool $berkasAda = true): void
    {
        DB::connection('datacenter')->table('sekolah')->insert([
            'nama_sekolah' => 'SMP Negeri 93',
            'logo' => $jalur,
        ]);

        if ($berkasAda) {
            Storage::disk('datacenter')->put($jalur, 'logo-sekolah-ini');
        }
    }
}
