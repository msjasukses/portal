@extends('layouts.siswa')

@section('title', 'Absensi Bulanan')

@section('content')
@php($rekap = $laporan['rekap'])
<section class="card">
  <div class="card__head">
    <h2><x-icon name="calendar" size="18" /> Rekap {{ $bulan->translatedFormat('F Y') }}</h2>
    <form class="toolbar" method="get" action="{{ route('siswa.absensi') }}">
      <select name="bulan" class="select select--sm" onchange="this.form.submit()" aria-label="Pilih bulan">
        @foreach ($daftarBulan as $pilihan)
          <option value="{{ $pilihan->format('Y-m') }}" @selected($pilihan->format('Y-m') === $bulan->format('Y-m'))>
            {{ $pilihan->translatedFormat('F Y') }}
          </option>
        @endforeach
      </select>
      <noscript><button class="btn btn--sm" type="submit">Tampilkan</button></noscript>
    </form>
  </div>

  @if ($laporan['rows'] === [])
    <p class="empty">Belum ada hari yang berjalan pada bulan ini.</p>
  @else
    <p class="section-note">
      Periode {{ $periode['dari']->translatedFormat('d M') }} &ndash; {{ $periode['sampai']->translatedFormat('d M Y') }}
      &middot; dihitung dengan aturan aplikasi Absensi
    </p>
    <div class="mini-stats">
      @foreach (App\Support\RekapAbsensi::LABEL as $status => $label)
        @continue(in_array($status, App\Support\RekapAbsensi::KHUSUS_GURU, true))
        <div class="mini-stat mini-stat--{{ App\Support\RekapAbsensi::WARNA[$status] }}">
          <p class="mini-stat__num">{{ $rekap[$status] }}</p>
          <p class="mini-stat__label">{{ $label }}</p>
        </div>
      @endforeach
    </div>
  @endif
</section>

@if ($laporan['rows'] !== [])
  <section class="card">
    <div class="card__head"><h2>Rincian Harian</h2></div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Jam Masuk</th>
            <th>Jam Pulang</th>
            <th>Status</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach (array_reverse($laporan['rows']) as $baris)
            <tr>
              <td class="nowrap">{{ $baris['tanggal']->format('d-m-Y') }}</td>
              <td>{{ $baris['tanggal']->translatedFormat('l') }}</td>
              <td>{{ $baris['jam_masuk'] ? substr($baris['jam_masuk'], 0, 5) : '-' }}</td>
              <td>{{ $baris['jam_pulang'] ? substr($baris['jam_pulang'], 0, 5) : '-' }}</td>
              <td>
                <span class="chip chip--{{ App\Support\RekapAbsensi::WARNA[$baris['status']] }}">
                  {{ App\Support\RekapAbsensi::LABEL[$baris['status']] }}
                </span>
              </td>
              <td class="wrap">
                {{ $baris['keterangan'] }}
                @if ($baris['shift'])
                  <small class="muted">(shift {{ $baris['shift'] }})</small>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endif
@endsection
