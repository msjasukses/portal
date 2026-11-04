@extends('layouts.siswa')

@section('title', 'Absen Barcode')

@section('content')
@if (! $aktif)
  <div class="alert alert--warning">
    <x-icon name="alert" size="16" />
    <span><strong>Absen barcode belum aktif.</strong> Admin sekolah perlu mengisi alamat dan kunci aplikasi Absensi di pengaturan portal.</span>
  </div>
@elseif ($pindaiSalah)
  <div class="alert alert--error">
    <x-icon name="x" size="16" />
    <span>Barcode tidak dikenali. Pastikan yang dipindai adalah barcode pada layar absensi sekolah.</span>
  </div>
@elseif ($verifikasi && ! $verifikasi['ok'])
  <div class="alert alert--error">
    <x-icon name="x" size="16" />
    <span>{{ $verifikasi['pesan'] ?? 'Barcode tidak berlaku lagi.' }}</span>
  </div>
@endif

@if ($aktif && $token !== null && $verifikasi && $verifikasi['ok'])
  <section class="card scan-ok">
    <p class="scan-ok__label"><x-icon name="check" size="16" /> Barcode terbaca</p>
    <h2 class="scan-ok__nama">{{ $siswa->nama_siswa }}</h2>
    <p class="muted">NISN {{ $siswa->nisn }}@if ($kelas) &middot; {{ $kelas }}@endif</p>

    <form method="post" action="{{ route('siswa.absen.store') }}" class="scan-ok__form">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <button type="submit" class="btn btn--primary btn--block btn--lg">
        <x-icon name="check" size="18" /> <span>Absen Sekarang</span>
      </button>
    </form>

    <p class="scan-ok__sisa">
      Barcode ini berlaku
      <strong data-hitung-mundur="{{ (int) ($verifikasi['sisa_detik'] ?? 0) }}">{{ (int) ($verifikasi['sisa_detik'] ?? 0) }} detik</strong>
      lagi. Lewat dari itu, pindai ulang barcode di layar.
    </p>
    <a class="btn btn--sm" href="{{ route('siswa.absen') }}"><x-icon name="refresh" size="14" /> Pindai Ulang</a>
  </section>
@elseif ($aktif)
  <section class="card">
    <div class="card__head">
      <h2><x-icon name="qr" size="18" /> Absen dengan Barcode</h2>
    </div>
    <p class="section-note">
      Pindai barcode yang tampil di layar absensi sekolah. Barcode berganti tiap 2 menit,
      jadi tangkapan layar yang dikirim teman tidak bisa dipakai.
    </p>

    {{-- Pemindai dalam portal. Disembunyikan sampai JS memastikan kamera bisa dipakai. --}}
    <div class="scan" data-scan hidden>
      <div data-scan-mulai>
        <button type="button" class="btn btn--primary btn--block btn--lg" data-scan-start>
          <x-icon name="camera" size="18" /> <span>Absen Sekarang</span>
        </button>
        <p class="scan__status" data-scan-status role="status"></p>
      </div>

      <div data-scan-kamera hidden>
        <div class="scan__frame">
          <video class="scan__video" data-scan-video playsinline muted></video>
          <div class="scan__sasaran" aria-hidden="true"></div>
        </div>
        <p class="scan__status">Arahkan kamera ke barcode pada layar absensi.</p>
        <button type="button" class="btn btn--block" data-scan-batal><span>Batal</span></button>
      </div>

      <form method="post" action="{{ route('siswa.absen.store') }}" class="scan__konfirmasi" data-scan-form hidden>
        @csrf
        <input type="hidden" name="token" data-scan-token>
        <p class="scan-ok__label"><x-icon name="check" size="16" /> Barcode terbaca</p>
        <button type="submit" class="btn btn--primary btn--block btn--lg">
          <x-icon name="check" size="18" /> <span>Absen</span>
        </button>
      </form>
    </div>

    {{-- Pesan bila kamera tidak bisa dipakai; diisi JS sesuai sebabnya. --}}
    <p class="scan__status scan__status--error" data-scan-halangan hidden></p>

    <details class="panduan" data-scan-panduan open>
      <summary>Atau pindai dengan aplikasi Kamera HP</summary>
      <ol class="langkah">
        <li>Buka aplikasi <strong>Kamera</strong> di HP kamu.</li>
        <li>Arahkan ke barcode pada layar absensi sampai muncul tautan.</li>
        <li>Ketuk tautannya — halaman ini akan terbuka dengan tombol <strong>Absen Sekarang</strong>.</li>
      </ol>
    </details>
  </section>

  <section class="card">
    <div class="card__head"><h2>Barcode Tidak Terbaca?</h2></div>
    <p class="section-note">Ketik kode yang tertera di bawah barcode pada layar absensi.</p>
    <form method="post" action="{{ route('siswa.absen.store') }}" class="toolbar">
      @csrf
      <input type="text" name="token" inputmode="latin" autocomplete="off" spellcheck="false"
             placeholder="Kode barcode" aria-label="Kode barcode" required>
      <button type="submit" class="btn btn--primary"><span>Kirim</span></button>
    </form>
  </section>
@endif

<section class="card">
  <div class="card__head">
    <h2><x-icon name="calendar" size="18" /> Absensi Hari Ini</h2>
    <a class="btn btn--sm" href="{{ route('siswa.absensi') }}">Rekap Bulanan <x-icon name="arrow-right" size="14" /></a>
  </div>
  @if ($hariIni === null)
    <p class="empty">Data absensi hari ini belum dapat dimuat.</p>
  @else
    <p class="section-note">{{ today()->translatedFormat('l, d F Y') }}</p>
    <dl class="kv">
      <dt>Status</dt>
      <dd>
        <span class="chip chip--{{ App\Support\RekapAbsensi::WARNA[$hariIni['status']] }}">
          {{ App\Support\RekapAbsensi::LABEL[$hariIni['status']] }}
        </span>
      </dd>
      <dt>Jam Masuk</dt>
      <dd>{{ $hariIni['jam_masuk'] ? substr($hariIni['jam_masuk'], 0, 5) : 'Belum absen' }}</dd>
      <dt>Jam Pulang</dt>
      <dd>{{ $hariIni['jam_pulang'] ? substr($hariIni['jam_pulang'], 0, 5) : 'Belum absen' }}</dd>
      @if ($hariIni['keterangan'])
        <dt>Keterangan</dt>
        <dd>{{ $hariIni['keterangan'] }}</dd>
      @endif
      <dt>Pindaian Berikutnya</dt>
      <dd>
        @if (! $hariIni['jam_masuk'])
          Akan dicatat sebagai <strong>absen masuk</strong>.
        @elseif (! $hariIni['jam_pulang'])
          Akan dicatat sebagai <strong>absen pulang</strong>.
        @else
          Masuk dan pulang sudah tercatat hari ini.
        @endif
      </dd>
    </dl>
    <p class="section-note">
      Jeda antar pindaian mengikuti aturan kartu RFID: absen pulang baru dihitung
      beberapa menit setelah tap terakhir.
    </p>
  @endif
</section>

<script src="{{ asset_v('js/siswa-absen.js') }}"></script>
@endsection
