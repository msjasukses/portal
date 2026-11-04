@php
    $menu = [
        ['route' => 'siswa.dashboard', 'active' => 'siswa.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
        ['route' => 'siswa.absen', 'active' => 'siswa.absen', 'label' => 'Absen Barcode', 'icon' => 'qr'],
        ['route' => 'siswa.absensi', 'active' => 'siswa.absensi', 'label' => 'Absensi Bulanan', 'icon' => 'calendar'],
        ['route' => 'siswa.agenda', 'active' => 'siswa.agenda', 'label' => 'Agenda Mengajar', 'icon' => 'book'],
        ['route' => 'siswa.tugas.index', 'active' => 'siswa.tugas.*', 'label' => 'Tugas', 'icon' => 'clipboard'],
        ['route' => 'siswa.materi', 'active' => 'siswa.materi', 'label' => 'Materi Online', 'icon' => 'video'],
        ['route' => 'siswa.jurnal.index', 'active' => 'siswa.jurnal.*', 'label' => 'Jurnal PKL / Magang', 'icon' => 'briefcase'],
        ['route' => 'siswa.prestasi', 'active' => 'siswa.prestasi', 'label' => 'Prestasi', 'icon' => 'award'],
        ['route' => 'siswa.pelanggaran', 'active' => 'siswa.pelanggaran', 'label' => 'Pelanggaran', 'icon' => 'alert'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') &middot; Portal Siswa</title>
<link rel="stylesheet" href="{{ asset_v('css/portal.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/siswa.css') }}">
</head>
<body>
<input type="checkbox" id="navToggle" class="nav-toggle" hidden>

<aside class="sidebar">
  @php($logo = upload_url(setting('site_logo')))
  <div @class(['sidebar__brand', 'sidebar__brand--logo' => $logo])>
    @if ($logo)
      <span class="sidebar__logo sidebar__logo--img"><img src="{{ $logo }}" alt=""></span>
    @else
      <span class="sidebar__logo"><x-icon name="graduation" size="20" /></span>
    @endif
    <div>
      <strong>Portal Siswa</strong>
      <small>{{ setting('site_name', config('app.name')) }}</small>
    </div>
  </div>

  <nav class="sidebar__nav">
    @foreach ($menu as $item)
      <a href="{{ route($item['route']) }}" @class(['nav-item', 'is-active' => request()->routeIs($item['active'])])>
        <x-icon :name="$item['icon']" size="18" /><span>{{ $item['label'] }}</span>
      </a>
    @endforeach
  </nav>

  <div class="sidebar__foot">
    @if ($landingUrl = config('portal.landing_url'))
      <a href="{{ $landingUrl }}" class="nav-item">
        <x-icon name="external" size="18" /><span>Halaman Utama</span>
      </a>
    @endif
    <form method="post" action="{{ route('siswa.logout') }}">
      @csrf
      <button type="submit" class="nav-item nav-item--danger">
        <x-icon name="logout" size="18" /><span>Keluar</span>
      </button>
    </form>
  </div>
</aside>

<div class="shell">
  <header class="topbar">
    <label for="navToggle" class="topbar__burger" aria-label="Menu"><x-icon name="menu" size="20" /></label>
    <h1 class="topbar__title">@yield('title')</h1>
    <div class="topbar__user">
      <span class="avatar">{{ initials($siswaLogin->nama_siswa) }}</span>
      <div>
        <strong>{{ $siswaLogin->nama_siswa }}</strong>
        <small>NISN {{ $siswaLogin->nisn }}@if ($kelasSiswa) &middot; {{ $kelasSiswa }}@endif</small>
      </div>
    </div>
  </header>

  <main class="content">
    @include('partials.alerts')
    @yield('content')
  </main>
</div>

<label for="navToggle" class="nav-backdrop"></label>
<script src="{{ asset_v('js/portal.js') }}"></script>
</body>
</html>
