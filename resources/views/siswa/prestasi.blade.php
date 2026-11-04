@extends('layouts.siswa')

@section('title', 'Prestasi')

@section('content')
<div class="mini-stats">
  <div class="mini-stat mini-stat--violet">
    <p class="mini-stat__num">{{ $prestasi->count() }}</p>
    <p class="mini-stat__label">Total prestasi</p>
  </div>
  <div class="mini-stat mini-stat--blue">
    <p class="mini-stat__num">{{ $perBidang->get('Akademik', 0) }}</p>
    <p class="mini-stat__label">Akademik</p>
  </div>
  <div class="mini-stat mini-stat--green">
    <p class="mini-stat__num">{{ $perBidang->get('Non-Akademik', 0) }}</p>
    <p class="mini-stat__label">Non-Akademik</p>
  </div>
</div>

<section class="card">
  <div class="card__head"><h2><x-icon name="award" size="18" /> Daftar Prestasi</h2></div>
  <p class="section-note">Prestasi yang dicatat sekolah di aplikasi Kesiswaan.</p>

  @if ($prestasi->isEmpty())
    <p class="empty">Belum ada prestasi yang tercatat. Terus semangat berprestasi!</p>
  @else
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Prestasi</th>
            <th>Bidang</th>
            <th>Tingkat</th>
            <th>Peringkat</th>
            <th>Penyelenggara</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($prestasi as $item)
            <tr>
              <td class="nowrap">{{ $item->tanggal?->translatedFormat('d M Y') ?? '-' }}</td>
              <td class="wrap">
                <strong>{{ $item->nama_prestasi }}</strong>
                @if ($item->keterangan)<small class="db muted pre-line">{{ $item->keterangan }}</small>@endif
              </td>
              <td><span @class(['chip', 'chip--blue' => $item->bidang === 'Akademik', 'chip--green' => $item->bidang !== 'Akademik'])>{{ $item->bidang }}</span></td>
              <td>{{ $item->tingkat }}</td>
              <td>{{ $item->peringkat ?: '-' }}</td>
              <td>{{ $item->penyelenggara ?: '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
