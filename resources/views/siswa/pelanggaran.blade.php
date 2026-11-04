@extends('layouts.siswa')

@section('title', 'Pelanggaran')

@section('content')
<div class="mini-stats">
  <div class="mini-stat mini-stat--red">
    <p class="mini-stat__num">{{ $totalPoin }}</p>
    <p class="mini-stat__label">Total poin pelanggaran</p>
  </div>
  <div class="mini-stat mini-stat--amber">
    <p class="mini-stat__num">{{ $pelanggaran->count() }}</p>
    <p class="mini-stat__label">Jumlah catatan</p>
  </div>
</div>

<section class="card">
  <div class="card__head"><h2><x-icon name="alert" size="18" /> Catatan Pelanggaran</h2></div>
  <p class="section-note">Pelanggaran tata tertib yang dicatat sekolah di aplikasi Kesiswaan.</p>

  @if ($pelanggaran->isEmpty())
    <p class="empty">Tidak ada catatan pelanggaran. Pertahankan!</p>
  @else
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Pelanggaran</th>
            <th>Jenis</th>
            <th>Poin</th>
            <th>Keterangan</th>
            <th>Tindak Lanjut</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($pelanggaran as $item)
            <tr>
              <td class="nowrap">{{ $item->tanggal->translatedFormat('d M Y') }}</td>
              <td><strong>{{ $item->dataPelanggaran->nama ?? '-' }}</strong></td>
              <td>{{ $item->dataPelanggaran->jenisPelanggaran->nama ?? '-' }}</td>
              <td><span class="chip chip--red">{{ $item->poin }}</span></td>
              <td class="wrap pre-line">{{ $item->keterangan ?: '-' }}</td>
              <td>{{ $item->tindak_lanjut ?: '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
