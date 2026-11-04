<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Belum Tersedia &middot; Portal Siswa</title>
<link rel="stylesheet" href="{{ asset_v('css/portal.css') }}">
</head>
<body class="auth">
<div class="auth__card">
  <span class="auth__logo"><x-icon name="database" size="22" /></span>
  <h1>Data belum dapat dimuat</h1>
  <p class="auth__sub">Aplikasi {{ $aplikasi }} sedang tidak dapat dihubungi.</p>
  <p class="muted">Silakan coba beberapa saat lagi. Bila masalah berlanjut, hubungi admin sekolah.</p>
  <a class="btn btn--primary btn--block" href="{{ url()->current() }}">Coba lagi</a>
  @if ($landingUrl = config('portal.landing_url'))
    <p class="auth__back"><a href="{{ $landingUrl }}">&larr; Kembali ke halaman utama</a></p>
  @endif
</div>
</body>
</html>
