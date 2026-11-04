@extends('layouts.siswa')

@section('title', 'Agenda Mengajar')

@section('content')
<section class="card">
  <div class="card__head">
    <h2><x-icon name="book" size="18" /> Agenda Mengajar di Kelasku</h2>
    <form class="toolbar" method="get" action="{{ route('siswa.agenda') }}">
      <input type="month" name="bulan" value="{{ $bulan?->format('Y-m') }}" aria-label="Bulan">
      <button class="btn btn--sm" type="submit"><x-icon name="search" size="14" /> Tampilkan</button>
      @if ($bulan)
        <a class="btn btn--sm" href="{{ route('siswa.agenda') }}">Semua</a>
      @endif
    </form>
  </div>
  <p class="section-note">Jurnal mengajar yang diisi guru di aplikasi Kurikulum, beserta kehadiranmu pada jam tersebut.</p>

  @if ($agenda->isEmpty())
    <p class="empty">Belum ada agenda mengajar{{ $bulan ? ' pada '.$bulan->translatedFormat('F Y') : '' }}.</p>
  @else
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Jam Ke</th>
            <th>Mata Pelajaran</th>
            <th>Guru</th>
            <th>Materi / Kegiatan</th>
            <th>Kehadiranku</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($agenda as $item)
            <tr>
              <td class="nowrap">
                {{ $item->waktu_pengisian->translatedFormat('D, d M Y') }}
                <small class="db muted">{{ $item->waktu_pengisian->format('H:i') }}</small>
              </td>
              <td>
                {{ $item->jam_ke ?: '-' }}
                @if ($item->jumlah_jam > 1)<small class="db muted">{{ $item->jumlah_jam }} jam</small>@endif
              </td>
              <td>{{ $item->mataPelajaran->nama_mapel ?? '-' }}</td>
              <td>
                {{ $item->guru->nama_ptk ?? '-' }}
                @if ($item->mengajar_sebagai === 'pengganti')<small class="db muted">Guru pengganti</small>@endif
              </td>
              <td class="wrap">
                <span class="pre-line">{{ $item->materi ?: '-' }}</span>
                @if ($item->catatan)<small class="db muted pre-line">Catatan: {{ $item->catatan }}</small>@endif
              </td>
              <td>
                @if ($status = $item->kehadiranSiswa($siswa->id))
                  <span class="chip chip--{{ App\Models\Kurikulum\AgendaMengajar::WARNA_KEHADIRAN[$status] ?? 'slate' }}">{{ $status }}</span>
                @else
                  <span class="muted">-</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $agenda->links('siswa.partials.pagination') }}
  @endif
</section>
@endsection
