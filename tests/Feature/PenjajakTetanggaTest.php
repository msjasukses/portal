<?php

namespace Tests\Feature;

use App\Support\PenjajakTetangga;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Portal menemukan sendiri alamat aplikasi ujian dan presensi.
 *
 * Dulu keduanya harus dituliskan ke `.env`, dan itu langkah yang paling
 * sering terlewat — karena satu-satunya akibatnya adalah menu yang diam-diam
 * tidak muncul di aplikasi ponsel. Yang diuji di sini bukan hanya bahwa
 * penjajakannya berhasil, melainkan juga bahwa ia tahu kapan harus berhenti:
 * tidak keluar dari host portal, dan tidak menimpa alamat yang sudah
 * dituliskan operator.
 */
class PenjajakTetanggaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['portal.ujian_url' => null, 'services.absensi.url' => null]);
    }

    public function test_the_exam_app_is_found_beside_the_portal_without_any_env(): void
    {
        // Yang membuktikan bukan nama aplikasinya — itu diganti tiap sekolah —
        // melainkan rute yang menuntut token. Portal sendiri menjawab 404 di
        // alamat yang sama, jadi ia tidak akan menunjuk dirinya sendiri.
        Http::fake([
            '*/cbt/api/v1/ujian' => Http::response('', 401),
            '*' => Http::response('', 404),
        ]);

        $this->assertSame(
            rtrim(url('/'), '/').'/cbt',
            (new PenjajakTetangga)->ujian(),
        );
    }

    public function test_an_address_written_by_the_operator_is_never_overruled(): void
    {
        config(['portal.ujian_url' => 'https://ujian.sekolah.sch.id/']);
        Http::fake(['*' => Http::response('', 401)]);

        $this->assertSame(
            'https://ujian.sekolah.sch.id',
            (new PenjajakTetangga)->ujian(),
        );

        // Tidak satu ketukan pun: alamat yang ditulis operator tidak perlu
        // dibuktikan, dan sekolah yang menaruh ujiannya di domain lain tidak
        // boleh membayar penjajakan yang pasti gagal.
        Http::assertNothingSent();
    }

    public function test_the_attendance_app_is_recognised_by_its_refusal(): void
    {
        // Kunci sengaja tidak dikirim. Jawaban "kunci salah" itu sendiri sudah
        // membuktikan berkasnya ada dan ia memang API barcode — portal tidak
        // perlu memegang rahasia apa pun untuk sekadar menemukannya.
        Http::fake([
            '*/presensi/barcode_api.php*' => Http::response(
                ['ok' => false, 'kode' => 'kunci_salah'], 401,
            ),
            '*' => Http::response('', 404),
        ]);

        $this->assertSame(
            rtrim(url('/'), '/').'/presensi',
            (new PenjajakTetangga)->presensi(),
        );
    }

    public function test_a_server_that_answers_everything_is_not_mistaken_for_a_neighbour(): void
    {
        // Peladen yang menjawab 200 untuk apa pun — halaman depan sekolah yang
        // menangkap semua alamat, misalnya — bukan aplikasi ujian. Diam lebih
        // baik daripada menunjuk menu yang pasti gagal dibuka.
        Http::fake(['*' => Http::response('halaman depan', 200)]);

        $this->assertNull((new PenjajakTetangga)->ujian());
        $this->assertNull((new PenjajakTetangga)->presensi());
    }

    public function test_nothing_outside_the_portals_own_host_is_ever_knocked(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        (new PenjajakTetangga)->ujian();
        (new PenjajakTetangga)->presensi();

        $inang = parse_url(url('/'), PHP_URL_HOST);

        Http::assertSentCount(7);
        Http::assertSent(fn ($permintaan) => parse_url($permintaan->url(), PHP_URL_HOST) === $inang);
    }

    public function test_a_fruitless_search_is_remembered_so_it_is_not_repeated(): void
    {
        // Arahan dipanggil tiap aplikasi dibuka. Tanpa mengingat kegagalan,
        // 300 ponsel pada jam yang sama berarti ribuan ketukan ke aplikasi
        // sebelah yang memang tidak ada.
        Http::fake(['*' => Http::response('', 404)]);

        $penjajak = new PenjajakTetangga;

        $this->assertNull($penjajak->ujian());
        $this->assertNull($penjajak->ujian());
        $this->assertNull($penjajak->ujian());

        Http::assertSentCount(4);
    }
}
