<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk &middot; Portal Siswa</title>
<link rel="stylesheet" href="{{ asset_v('css/portal.css') }}">
</head>
<body class="auth">
<form class="auth__card" method="post" action="{{ route('siswa.login.store') }}">
  @csrf
  @if ($logo = upload_url(setting('site_logo')))
    <span class="auth__logo auth__logo--img"><img src="{{ $logo }}" alt="Logo {{ setting('site_name', config('app.name')) }}"></span>
  @else
    <span class="auth__logo"><x-icon name="graduation" size="22" /></span>
  @endif
  <h1>Portal Siswa</h1>
  <p class="auth__sub">{{ setting('site_name', config('app.name')) }}</p>

  @include('partials.alerts')

  <label class="field">
    <span>NISN</span>
    <input type="text" name="nisn" required autofocus value="{{ old('nisn') }}" autocomplete="username" inputmode="numeric">
  </label>

  <label class="field">
    <span>Password</span>
    <input type="password" name="password" required autocomplete="current-password">
  </label>

  <button class="btn btn--primary btn--block" type="submit">Masuk</button>
  <p class="auth__hint">Gunakan NISN dan password akun Data Center sekolah.</p>
  @if ($landingUrl = config('portal.landing_url'))
    <p class="auth__back"><a href="{{ $landingUrl }}">&larr; Kembali ke halaman utama</a></p>
  @endif
</form>
</body>
</html>
