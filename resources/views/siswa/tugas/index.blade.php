@extends('layouts.siswa')

@section('title', 'Tugas')

@section('content')
<section class="card">
  <div class="card__head">
    <h2><x-icon name="clipboard" size="18" /> Tugas Kelasku</h2>
    <form class="toolbar" method="get" action="{{ route('siswa.tugas.index') }}">
      <input type="search" name="q" value="{{ $cari }}" placeholder="Cari tugas..." aria-label="Cari tugas">
      <button class="btn btn--sm" type="submit"><x-icon name="search" size="14" /></button>
      @if ($cari !== '')
        <a class="btn btn--sm" href="{{ route('siswa.tugas.index') }}" title="Reset pencarian"><x-icon name="x" size="14" /></a>
      @endif
    </form>
  </div>

  @if ($tugas->isEmpty())
    <p class="empty">{{ $cari !== '' ? 'Tidak ada tugas yang cocok dengan pencarian.' : 'Belum ada tugas untuk kelasmu.' }}</p>
  @else
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Judul Tugas</th>
            <th>Mata Pelajaran</th>
            <th>Guru</th>
            <th>Mulai</th>
            <th>Batas Akhir</th>
            <th>Status</th>
            <th class="ta-r">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tugas as $item)
            @php($kumpul = $pengumpulan->get($item->id))
            <tr>
              <td>{{ $tugas->firstItem() + $loop->index }}</td>
              <td><strong>{{ $item->judul }}</strong></td>
              <td>{{ $item->mataPelajaran->nama_mapel ?? '-' }}</td>
              <td>{{ $item->guru->nama_ptk ?? '-' }}</td>
              <td class="nowrap">{{ $item->tanggal_mulai?->translatedFormat('d M Y') ?? '-' }}</td>
              <td class="nowrap">
                {{ $item->tanggal_selesai?->translatedFormat('d M Y') ?? '-' }}
                @if (! $kumpul && $item->lewatBatas())<small class="db text-danger">Batas lewat</small>@endif
              </td>
              <td>@include('siswa.partials.status-tugas', ['pengumpulan' => $kumpul])</td>
              <td class="ta-r">
                <a @class(['btn btn--sm', 'btn--primary' => ! $kumpul]) href="{{ route('siswa.tugas.show', $item) }}">
                  <x-icon :name="$kumpul ? 'eye' : 'upload'" size="14" /> {{ $kumpul ? 'Lihat' : 'Kumpulkan' }}
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $tugas->links('siswa.partials.pagination') }}
  @endif
</section>
@endsection
