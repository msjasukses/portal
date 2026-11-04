<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Datacenter\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Login Portal Siswa memakai akun siswa aplikasi Data Center (NISN + password).
 * Aturan kunci akun, status akun, dan pencatatan login sama dengan endpoint
 * verifikasi siswa di Data Center.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('siswa.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'nisn' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:100'],
        ], [], [
            'nisn' => 'NISN',
        ]);

        $siswa = Siswa::where('nisn', $credentials['nisn'])->first();

        if (! $siswa) {
            return $this->gagal($request, 'NISN atau password salah.');
        }

        if ($siswa->sedangTerkunci()) {
            $menit = max(1, (int) ceil(now()->diffInMinutes($siswa->locked_until)));

            return $this->gagal($request, "Akun dikunci sementara karena terlalu banyak percobaan. Coba lagi dalam {$menit} menit.");
        }

        if (! $siswa->akunAktif()) {
            return $this->gagal($request, 'Akun siswa tidak aktif. Hubungi admin Data Center.');
        }

        // Hash selain bcrypt (mis. password lama yang belum di-hash) dianggap salah.
        $cocok = filled($siswa->password)
            && rescue(fn () => Hash::check($credentials['password'], $siswa->password), false, report: false);

        if (! $cocok) {
            $gagal = (int) $siswa->failed_login_count + 1;
            $this->catatLogin($siswa, $gagal >= Siswa::MAKS_GAGAL_LOGIN
                ? ['failed_login_count' => 0, 'locked_until' => now()->addMinutes(Siswa::MENIT_KUNCI)]
                : ['failed_login_count' => $gagal]);

            return $this->gagal($request, 'NISN atau password salah.');
        }

        $this->catatLogin($siswa, ['failed_login_count' => 0, 'locked_until' => null, 'last_seen_at' => now()]);

        Auth::guard('siswa')->login($siswa);
        $request->session()->regenerate();

        // Hanya kembali ke halaman portal siswa yang sempat diminta, bukan panel admin.
        $tujuan = $request->session()->pull('url.intended');
        $dashboard = route('siswa.dashboard');

        return redirect()->to(is_string($tujuan) && str_starts_with($tujuan, $dashboard) ? $tujuan : $dashboard);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('siswa')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('siswa.login');
    }

    private function gagal(Request $request, string $pesan): RedirectResponse
    {
        return back()->withInput($request->only('nisn'))->withErrors(['nisn' => $pesan]);
    }

    /**
     * Catat penanda login di Data Center. Bila user database Data Center hanya
     * punya hak baca, pencatatan dilewati tanpa menggagalkan login.
     *
     * @param  array<string, mixed>  $kolom
     */
    private function catatLogin(Siswa $siswa, array $kolom): void
    {
        rescue(fn () => $siswa->forceFill($kolom)->save());
    }
}
