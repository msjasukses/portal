@extends('layouts.siswa')

@section('title', 'Dashboard')

@section('content')
<div class="welcome">
  <div>
    <h2>Halo, {{ $siswa->nama_siswa }}</h2>
    <p>
      {{ $rombel?->nama_rombel ?? 'Belum ditempatkan di kelas pada tahun ajaran aktif' }}
      &middot; {{ today()->translatedFormat('l, d F Y') }}
    </p>
  </div>
  <a class="btn btn--sm" href="{{ route('siswa.tugas.index') }}"><x-icon name="clipboard" size="15" /> Lihat Tugas</a>
</div>

<section class="card">
  <div class="card__head">
    <h2><x-icon name="calendar" size="18" /> Absensi Bulan {{ $periode['dari']->translatedFormat('F Y') }}</h2>
    <a class="btn btn--sm" href="{{ route('siswa.absensi') }}">Detail <x-icon name="arrow-right" size="14" /></a>
  </div>
  @if ($rekapAbsensi === null)
    <p class="muted">Data absensi belum dapat dimuat dari aplikasi Absensi.</p>
  @else
    <p class="section-note">
      Periode {{ $periode['dari']->translatedFormat('d M') }} &ndash; {{ $periode['sampai']->translatedFormat('d M Y') }} &middot; data aplikasi Absensi
    </p>
    <div class="mini-stats">
      <div class="mini-stat mini-stat--green">
        <p class="mini-stat__num">{{ $rekapAbsensi['hadir'] + $rekapAbsensi['terlambat'] }}</p>
        <p class="mini-stat__label">Hadir</p>
        @if ($rekapAbsensi['terlambat'] > 0)
          <small class="muted">{{ $rekapAbsensi['terlambat'] }} di antaranya terlambat</small>
        @endif
      </div>
      <div class="mini-stat mini-stat--red">
        <p class="mini-stat__num">{{ $rekapAbsensi['alpha'] }}</p>
        <p class="mini-stat__label">Alpha</p>
      </div>
      <div class="mini-stat mini-stat--sky">
        <p class="mini-stat__num">{{ $rekapAbsensi['izin'] }}</p>
        <p class="mini-stat__label">Izin</p>
      </div>
      <div class="mini-stat mini-stat--blue">
        <p class="mini-stat__num">{{ $rekapAbsensi['sakit'] }}</p>
        <p class="mini-stat__label">Sakit</p>
      </div>
      <div class="mini-stat mini-stat--violet">
        <p class="mini-stat__num">{{ $rekapAbsensi['magang'] }}</p>
        <p class="mini-stat__label">Magang</p>
      </div>
    </div>
  @endif
</section>

<div class="grid-2">
  <section class="card">
    <div class="card__head">
      <h2><x-icon name="clipboard" size="18" /> Tugas</h2>
      <a class="btn btn--sm" href="{{ route('siswa.tugas.index') }}">Semua <x-icon name="arrow-right" size="14" /></a>
    </div>
    @if ($tugas === null)
      <p class="muted">Data tugas belum dapat dimuat dari aplikasi Kurikulum.</p>
    @else
      <div class="mini-stats">
        <div class="mini-stat mini-stat--red">
          <p class="mini-stat__num">{{ $tugas['ringkasan']['belum'] }}</p>
          <p class="mini-stat__label">Belum dikumpulkan</p>
        </div>
        <div class="mini-stat mini-stat--green">
          <p class="mini-stat__num">{{ $tugas['ringkasan']['dikumpulkan'] }}</p>
          <p class="mini-stat__label">Sudah dikumpulkan</p>
        </div>
        <div class="mini-stat mini-stat--blue">
          <p class="mini-stat__num">{{ $tugas['ringkasan']['dinilai'] }}</p>
          <p class="mini-stat__label">Sudah dinilai</p>
        </div>
      </div>
      @if ($tugas['terbaru']->isEmpty())
        <p class="empty">Belum ada tugas.</p>
      @else
        <ul class="list">
          @foreach ($tugas['terbaru'] as $item)
            <li>
              <span class="list__icon ic-indigo"><x-icon name="file-text" size="16" /></span>
              <div class="list__body">
                <strong><a href="{{ route('siswa.tugas.show', $item) }}">{{ $item->judul }}</a></strong>
                <small>
                  {{ $item->mataPelajaran->nama_mapel ?? '-' }}
                  &middot; Batas {{ $item->tanggal_selesai?->translatedFormat('d M Y') ?? '-' }}
                </small>
              </div>
              @include('siswa.partials.status-tugas', ['pengumpulan' => $tugas['pengumpulan']->get($item->id)])
            </li>
          @endforeach
        </ul>
      @endif
    @endif
  </section>

  <section class="card">
    <div class="card__head">
      <h2><x-icon name="book" size="18" /> Agenda Mengajar Terbaru</h2>
      <a class="btn btn--sm" href="{{ route('siswa.agenda') }}">Semua <x-icon name="arrow-right" size="14" /></a>
    </div>
    @if ($agenda === null)
      <p class="muted">Data agenda belum dapat dimuat dari aplikasi Kurikulum.</p>
    @elseif ($agenda->isEmpty())
      <p class="empty">Belum ada agenda mengajar di kelasmu.</p>
    @else
      <ul class="list">
        @foreach ($agenda as $item)
          <li>
            <span class="list__icon ic-teal"><x-icon name="book" size="16" /></span>
            <div class="list__body">
              <strong>{{ $item->mataPelajaran->nama_mapel ?? '-' }}</strong>
              <small>
                {{ $item->waktu_pengisian->translatedFormat('d M Y, H:i') }} &middot; {{ $item->guru->nama_ptk ?? '-' }}
                @if ($item->materi) &middot; {{ Str::limit($item->materi, 50) }}@endif
              </small>
            </div>
            @if ($status = $item->kehadiranSiswa($siswa->id))
              <span class="chip chip--{{ App\Models\Kurikulum\AgendaMengajar::WARNA_KEHADIRAN[$status] ?? 'slate' }}">{{ $status }}</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endif
  </section>
</div>

<section class="card">
  <div class="card__head">
    <h2><x-icon name="video" size="18" /> Materi Online Terbaru</h2>
    <a class="btn btn--sm" href="{{ route('siswa.materi') }}">Semua <x-icon name="arrow-right" size="14" /></a>
  </div>
  @if ($materi === null)
    <p class="muted">Data materi belum dapat dimuat dari aplikasi Kurikulum.</p>
  @elseif ($materi->isEmpty())
    <p class="empty">Belum ada materi untuk kelasmu.</p>
  @else
    <ul class="list">
      @foreach ($materi as $item)
        <li>
          <span class="list__icon ic-rose"><x-icon name="video" size="16" /></span>
          <div class="list__body">
            <strong>{{ $item->judul }}</strong>
            <small>{{ $item->mataPelajaran->nama_mapel ?? '-' }} &middot; {{ $item->guru->nama_ptk ?? '-' }}</small>
          </div>
          <div class="row-actions">
            @if ($item->file)
              <a class="btn btn--sm" href="{{ route('siswa.berkas.materi', $item) }}" target="_blank" rel="noopener"><x-icon name="download" size="14" /> File</a>
            @endif
            @if ($video = $item->tautanVideo())
              <a class="btn btn--sm btn--danger" href="{{ $video }}" target="_blank" rel="noopener"><x-icon name="video" size="14" /> Video</a>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
