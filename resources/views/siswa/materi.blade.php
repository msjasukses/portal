@extends('layouts.siswa')

@section('title', 'Materi Online')

@section('content')
<section class="card">
  <div class="card__head">
    <form class="toolbar" method="get" action="{{ route('siswa.materi') }}">
      <input type="search" name="q" value="{{ $cari }}" placeholder="Cari..." aria-label="Cari materi">
      <button class="btn btn--sm" type="submit"><x-icon name="search" size="14" /></button>
      @if ($cari !== '')
        <a class="btn btn--sm" href="{{ route('siswa.materi') }}" title="Reset filter"><x-icon name="x" size="14" /></a>
      @endif
    </form>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Judul Materi</th>
          <th>Mata Pelajaran</th>
          <th>Kelas</th>
          <th>Guru Pengunggah</th>
          <th>Isi Materi</th>
          <th class="ta-r">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($materi as $item)
          <tr>
            <td>{{ $materi->firstItem() + $loop->index }}</td>
            <td>{{ Str::limit($item->judul, 60) }}</td>
            <td>{{ Str::limit($item->mataPelajaran->nama_mapel ?? '', 60) }}</td>
            <td>{{ Str::limit($item->kelas->nama_rombel ?? '', 60) }}</td>
            <td>{{ Str::limit($item->guru->nama_ptk ?? '', 60) }}</td>
            <td>
              <div class="chips">
                @foreach ($item->penanda() as $penanda)
                  <span class="chip chip--{{ $penanda['warna'] }}">{{ $penanda['teks'] }}</span>
                @endforeach
              </div>
            </td>
            <td>
              <div class="row-actions">
                @if ($item->file)
                  <a class="btn btn--sm" href="{{ route('siswa.berkas.materi', $item) }}" target="_blank" rel="noopener" title="File">
                    <x-icon name="download" size="14" /> File
                  </a>
                @endif
                @if ($video = $item->tautanVideo())
                  <a class="btn btn--sm btn--danger" href="{{ $video }}" target="_blank" rel="noopener" title="Video">
                    <x-icon name="video" size="14" /> Video
                  </a>
                @endif
                @if (! $item->file && ! $video)
                  <span class="muted">Lihat saja</span>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="empty">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $materi->links('siswa.partials.pagination') }}
</section>
@endsection
