<?php

namespace Tests\Feature\Siswa;

use App\Models\Datacenter\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchoolDatabases;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use InteractsWithSchoolDatabases, RefreshDatabase;

    public function test_guest_is_redirected_to_student_login(): void
    {
        $this->get(route('siswa.dashboard'))->assertRedirect(route('siswa.login'));
    }

    public function test_student_logs_in_with_datacenter_nisn_and_password(): void
    {
        $this->freezeSecond();
        $siswa = Siswa::factory()->create(['failed_login_count' => 3]);

        $this->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'password'])
            ->assertRedirect(route('siswa.dashboard'));

        $this->assertAuthenticatedAs($siswa, 'siswa');
        $siswa->refresh();
        $this->assertSame(0, (int) $siswa->failed_login_count);
        $this->assertTrue($siswa->last_seen_at->equalTo(now()));
    }

    public function test_wrong_password_is_rejected_and_counted(): void
    {
        $siswa = Siswa::factory()->create();

        $this->from(route('siswa.login'))
            ->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'salah'])
            ->assertRedirect(route('siswa.login'))
            ->assertSessionHasErrors(['nisn' => 'NISN atau password salah.']);

        $this->assertGuest('siswa');
        $this->assertSame(1, (int) $siswa->refresh()->failed_login_count);
    }

    public function test_fifth_wrong_password_locks_account_for_fifteen_minutes(): void
    {
        $this->freezeSecond();
        $siswa = Siswa::factory()->create(['failed_login_count' => Siswa::MAKS_GAGAL_LOGIN - 1]);

        $this->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'salah']);

        $siswa->refresh();
        $this->assertSame(0, (int) $siswa->failed_login_count);
        $this->assertTrue($siswa->locked_until->equalTo(now()->addMinutes(Siswa::MENIT_KUNCI)));
    }

    public function test_locked_account_is_rejected_even_with_correct_password(): void
    {
        $siswa = Siswa::factory()->terkunci()->create();

        $this->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'password'])
            ->assertSessionHasErrors(['nisn' => 'Akun dikunci sementara karena terlalu banyak percobaan. Coba lagi dalam 10 menit.']);

        $this->assertGuest('siswa');
    }

    public function test_inactive_account_is_rejected(): void
    {
        $siswa = Siswa::factory()->nonaktif()->create();

        $this->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'password'])
            ->assertSessionHasErrors(['nisn' => 'Akun siswa tidak aktif. Hubungi admin Data Center.']);

        $this->assertGuest('siswa');
    }

    public function test_student_returns_to_requested_portal_page_after_login(): void
    {
        $siswa = Siswa::factory()->create();

        $this->get(route('siswa.tugas.index'))->assertRedirect(route('siswa.login'));

        $this->post(route('siswa.login.store'), ['nisn' => $siswa->nisn, 'password' => 'password'])
            ->assertRedirect(route('siswa.tugas.index'));
    }

    public function test_student_logs_out(): void
    {
        $this->actingAs(Siswa::factory()->create(), 'siswa')
            ->post(route('siswa.logout'))
            ->assertRedirect(route('siswa.login'));

        $this->assertGuest('siswa');
    }
}
