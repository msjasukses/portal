<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aplikasi yang diberi alamat portal padahal yang dibutuhkannya alamat server
 * ujian harus diberi tahu, bukan dibiarkan gagal tanpa sebab.
 *
 * Aplikasi rilis 1.0.0 tidak memeriksa nama aplikasi pada `/api/ping`, sehingga
 * alamat portal yang terketik di layar pengaturannya lolos sampai layar masuk —
 * kredensialnya pun sama, karena keduanya membaca akun dari Data Center. Yang
 * mati kemudian adalah daftar ujiannya, dengan 404 bawaan Laravel yang hanya
 * berbunyi "route not found". Dari layar siswa, itu tak bisa dibedakan dari
 * server yang rusak.
 *
 * Portal tidak bisa melayani ujian, dan tidak boleh berpura-pura bisa. Yang
 * bisa dilakukannya hanya satu: mengatakan apa yang salah dengan kalimat yang
 * sampai ke layar siswa.
 */
class AlamatSalahTest extends TestCase
{
    public function test_daftar_ujian_dijawab_dengan_sebabnya_bukan_route_not_found(): void
    {
        $this->getJson('/api/v1/ujian')
            ->assertNotFound()
            ->assertJsonPath('ok', false)
            ->assertJsonFragment(['pesan' => 'Alamat ini portal sekolah, bukan server ujian. Buka pengaturan alamat di aplikasi, lalu masukkan alamat aplikasi ujian sekolah Anda.']);
    }

    public function test_seluruh_jalur_ujian_ikut_dijawab_begitu(): void
    {
        // Aplikasi lama memanggil /mulai, /lembar, /jawaban, /denyut, /keluar,
        // /pelanggaran, /pengawasan, /selesai, dan /hasil. Satu pun yang lolos
        // sebagai 404 kosong berarti siswa yang sudah masuk tetap kehilangan
        // kalimatnya tepat pada langkah berikutnya.
        foreach (['mulai', 'lembar', 'jawaban', 'denyut', 'keluar', 'pelanggaran', 'pengawasan', 'selesai', 'hasil'] as $aksi) {
            $this->getJson("/api/v1/ujian/7/$aksi")
                ->assertNotFound()
                ->assertJsonPath('ok', false);
        }
    }

    public function test_kiriman_jawaban_pun_dijawab_begitu_bukan_405(): void
    {
        // Aplikasi lama mengirim jawaban dengan POST. Rute yang hanya menerima
        // GET akan membalas 405 tanpa pesan, dan kalimatnya hilang lagi.
        $this->postJson('/api/v1/ujian/7/jawaban', ['butir_id' => 1, 'jawaban' => 'A'])
            ->assertNotFound()
            ->assertJsonPath('ok', false);
    }

    public function test_rute_portal_yang_sungguhan_tidak_terbayangi(): void
    {
        // Jalur tangkapan itu duduk di grup yang sama dengan rute portal. Yang
        // dijaga di sini: ia tidak menelan rute lain, dan menu berpagar token
        // tetap menjawab 401 - bukan 404 dengan kalimat tentang alamat.
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/materi')->assertUnauthorized();
        $this->getJson('/api/ping')->assertOk()->assertJsonPath('aplikasi', 'Portal Siswa');
    }
}
