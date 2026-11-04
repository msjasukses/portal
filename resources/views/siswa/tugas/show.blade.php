@extends('layouts.siswa')

@section('title', 'Kumpulkan Tugas')

@section('content')
@php($lewatBatas = $tugas->lewatBatas())
<section class="card">
  <div class="card__head">
    <h2><x-icon name="file-text" size="18" /> {{ $tugas->judul }}</h2>
    @if ($tugas->tanggal_selesai)
      <span @class(['chip', 'chip--red' => $lewatBatas, 'chip--green' => ! $lewatBatas])>
        Batas akhir: {{ $tugas->tanggal_selesai->translatedFormat('d F Y') }}{{ $lewatBatas ? ' (sudah lewat)' : '' }}
      </span>
    @endif
  </div>
  <dl class="kv">
    <dt>Mata Pelajaran</dt>
    <dd>{{ $tugas->mataPelajaran->nama_mapel ?? '-' }}</dd>
    <dt>Kelas</dt>
    <dd>{{ $tugas->kelas->nama_rombel ?? '-' }}</dd>
    <dt>Guru</dt>
    <dd>{{ $tugas->guru->nama_ptk ?? '-' }}</dd>
    @if (filled($tugas->deskripsi))
      <dt>Instruksi</dt>
      <dd class="prose">{!! App\Support\TeksKaya::html($tugas->deskripsi) !!}</dd>
    @endif
    @if ($tugas->file_lampiran)
      <dt>Lampiran Guru</dt>
      <dd><a class="btn btn--sm" href="{{ route('siswa.berkas.tugas', $tugas) }}" target="_blank" rel="noopener"><x-icon name="download" size="14" /> Unduh lampiran</a></dd>
    @endif
  </dl>
</section>

@if ($pengumpulan?->sudahDinilai())
  <section class="card">
    <div class="card__head"><h2><x-icon name="award" size="18" /> Sudah Dinilai</h2></div>
    <p class="big-score">{{ $pengumpulan->nilai }}</p>
    @if (filled($pengumpulan->umpan_balik))
      <p><strong>Umpan balik guru:</strong></p>
      <p class="pre-line">{{ $pengumpulan->umpan_balik }}</p>
    @endif
  </section>
@endif

<section class="card">
  <div class="card__head">
    <h2><x-icon name="upload" size="18" /> {{ $pengumpulan ? 'Jawaban Saya' : 'Kumpulkan Jawaban' }}</h2>
    @if ($pengumpulan?->dikumpulkan_pada)
      <span @class(['chip', 'chip--amber' => $pengumpulan->terlambat, 'chip--green' => ! $pengumpulan->terlambat])>
        Dikumpulkan {{ $pengumpulan->dikumpulkan_pada->translatedFormat('d F Y H:i') }}{{ $pengumpulan->terlambat ? ' (terlambat)' : '' }}
      </span>
    @endif
  </div>

  @if ($pengumpulan?->sudahDinilai())
    <p class="muted">Tugas sudah dinilai guru sehingga jawaban tidak bisa diubah lagi.</p>
    @if (filled($pengumpulan->catatan))
      <p><strong>Catatan yang dikirim:</strong></p>
      <p class="pre-line">{{ $pengumpulan->catatan }}</p>
    @endif
    @if ($pengumpulan->file)
      <a class="btn btn--sm" href="{{ route('siswa.berkas.pengumpulan', $pengumpulan) }}" target="_blank" rel="noopener">
        <x-icon name="download" size="14" /> File yang dikirim
      </a>
    @endif
  @else
    @if ($lewatBatas)
      <div class="alert alert--warning">
        <x-icon name="clock" size="16" />
        <span>Batas waktu sudah lewat. Tugas tetap bisa dikumpulkan, tetapi akan ditandai <strong>terlambat</strong>.</span>
      </div>
    @endif

    <form class="stack" method="post" action="{{ route('siswa.tugas.store', $tugas) }}" enctype="multipart/form-data" data-uploading>
      @csrf
      <label class="field">
        <span>Catatan / Jawaban</span>
        <textarea name="catatan" rows="6" placeholder="Tulis jawaban atau keterangan singkat tentang tugasmu...">{{ old('catatan', $pengumpulan->catatan ?? '') }}</textarea>
      </label>
      <label class="field">
        <span>File Jawaban <em>(opsional)</em></span>
        <input type="file" name="file" data-max-bytes="10485760" data-max-label="10 MB"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.jpg,.jpeg,.png">
        <small class="muted">
          Maksimal 10 MB. Format: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, zip, rar, jpg, png.
          @if ($pengumpulan?->file)
            File saat ini: <a href="{{ route('siswa.berkas.pengumpulan', $pengumpulan) }}" target="_blank" rel="noopener">lihat</a>
            &mdash; mengunggah file baru akan menggantikannya.
          @endif
        </small>
      </label>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit"><x-icon name="upload" size="16" /><span>{{ $pengumpulan ? 'Perbarui Jawaban' : 'Kumpulkan Tugas' }}</span></button>
        <a class="btn" href="{{ route('siswa.tugas.index') }}">Kembali</a>
      </div>
    </form>
  @endif
</section>
@endsection
