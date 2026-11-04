@extends('layouts.siswa')

@section('title', 'Detail Jurnal PKL')

@section('content')
<div class="form-actions">
  <a class="btn btn--sm" href="{{ route('siswa.jurnal.index') }}"><x-icon name="arrow-left" size="14" /> Kembali</a>
</div>

<div class="grid-side">
  <section class="card">
    <div class="card__head">
      <div>
        <h2>{{ $item->tanggal->translatedFormat('l, d F Y') }}</h2>
        <small class="muted">
          @if ($item->jam_mulai){{ substr($item->jam_mulai, 0, 5) }}&ndash;{{ substr((string) $item->jam_selesai, 0, 5) }} &middot; @endif
          {{ $item->tempat }} &middot; Pembimbing: {{ $jadwal?->pembimbing ?: '-' }}
        </small>
      </div>
      <span class="chip chip--{{ $item->warnaStatus() }}">{{ $item->status }}</span>
    </div>

    <div class="stack">
      <div>
        <strong>Kegiatan</strong>
        <p class="pre-line">{{ $item->kegiatan }}</p>
      </div>
      @if ($item->hasil)
        <div>
          <strong>Hasil / Kompetensi</strong>
          <p class="pre-line">{{ $item->hasil }}</p>
        </div>
      @endif
      @if ($item->kendala)
        <div>
          <strong>Kendala</strong>
          <p class="pre-line">{{ $item->kendala }}</p>
        </div>
      @endif
      @if ($item->foto)
        <a href="{{ route('siswa.berkas.jurnal', $item) }}" target="_blank" rel="noopener">
          <img class="photo" src="{{ route('siswa.berkas.jurnal', $item) }}" alt="Foto kegiatan">
        </a>
      @endif
    </div>

    @if ($item->bisaDiubahSiswa())
      <hr class="sep sep--lg">
      <div class="form-actions">
        <a class="btn btn--sm btn--success" href="{{ route('siswa.jurnal.form', ['tanggal' => $item->tanggal->toDateString()]) }}">
          <x-icon name="edit" size="14" /> Ubah
        </a>
        <form method="post" action="{{ route('siswa.jurnal.destroy', $item) }}" data-confirm="Hapus jurnal ini?">
          @csrf
          @method('DELETE')
          <button class="btn btn--sm btn--danger" type="submit"><x-icon name="trash" size="14" /> Hapus</button>
        </form>
      </div>
    @endif
  </section>

  <section class="card">
    <div class="card__head"><h2>Pemeriksaan Pembimbing</h2></div>
    <p class="muted">Catatan:</p>
    <p class="pre-line">{{ $item->catatan_pembimbing ?: 'Belum ada catatan.' }}</p>
    @if ($item->diperiksa_pada)
      <small class="muted">Diperiksa {{ $pemeriksa }} &middot; {{ $item->diperiksa_pada->translatedFormat('d M Y H:i') }}</small>
    @endif
  </section>
</div>
@endsection
