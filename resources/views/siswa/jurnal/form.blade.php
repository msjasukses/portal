@extends('layouts.siswa')

@section('title', ($item ? 'Ubah' : 'Isi').' Jurnal PKL')

@section('content')
<div class="form-actions">
  <a class="btn btn--sm" href="{{ route('siswa.jurnal.index') }}"><x-icon name="arrow-left" size="14" /> Kembali</a>
</div>

@if ($item?->status === App\Models\Kurikulum\JurnalPkl::STATUS_REVISI)
  <div class="alert alert--warning">
    <x-icon name="alert" size="16" />
    <span><strong>Perlu revisi.</strong> {{ $item->catatan_pembimbing ?: 'Perbaiki jurnal lalu simpan kembali.' }}</span>
  </div>
@endif

<section class="card">
  <dl class="kv">
    <dt>Tanggal</dt>
    <dd>{{ $tanggal->translatedFormat('l, d F Y') }}</dd>
    <dt>Tempat</dt>
    <dd>{{ $jadwal->tempat }}</dd>
    <dt>Pembimbing</dt>
    <dd>{{ $jadwal->pembimbing ?: '-' }}</dd>
  </dl>
  <hr class="sep sep--lg">

  <form method="post" action="{{ route('siswa.jurnal.store') }}" enctype="multipart/form-data" class="stack" data-uploading>
    @csrf
    <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">

    <div class="form-grid">
      <label class="field">
        <span>Jam Mulai</span>
        <input type="time" name="jam_mulai" value="{{ old('jam_mulai', $item?->jam_mulai ? substr($item->jam_mulai, 0, 5) : '') }}">
      </label>
      <label class="field">
        <span>Jam Selesai</span>
        <input type="time" name="jam_selesai" value="{{ old('jam_selesai', $item?->jam_selesai ? substr($item->jam_selesai, 0, 5) : '') }}">
      </label>
    </div>

    <label class="field">
      <span>Kegiatan / Pekerjaan yang Dilakukan <em>(wajib)</em></span>
      <textarea name="kegiatan" rows="5" required placeholder="Uraikan kegiatan yang kamu kerjakan hari ini">{{ old('kegiatan', $item?->kegiatan) }}</textarea>
    </label>

    <div class="form-grid">
      <label class="field">
        <span>Hasil / Kompetensi yang Diperoleh</span>
        <textarea name="hasil" rows="3">{{ old('hasil', $item?->hasil) }}</textarea>
      </label>
      <label class="field">
        <span>Kendala</span>
        <textarea name="kendala" rows="3">{{ old('kendala', $item?->kendala) }}</textarea>
      </label>
    </div>

    <label class="field">
      <span>Foto Kegiatan <em>(opsional, gambar maks. 5 MB)</em></span>
      <input type="file" name="foto" accept="image/*" data-max-bytes="5242880" data-max-label="5 MB" data-preview="#fotoPreview">
    </label>
    <div class="preview preview--photo" id="fotoPreview" @if (! $item?->foto) hidden @endif>
      <img src="{{ $item?->foto ? route('siswa.berkas.jurnal', $item) : '' }}" alt="Foto kegiatan">
    </div>

    <div class="form-actions">
      <button class="btn btn--primary" type="submit"><x-icon name="check" size="16" /><span>Simpan Jurnal</span></button>
    </div>
  </form>
</section>
@endsection
