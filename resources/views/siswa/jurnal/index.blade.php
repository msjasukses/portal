@extends('layouts.siswa')

@section('title', 'Jurnal PKL / Magang')

@section('content')
@if ($jadwalList->isEmpty())
  <div class="alert alert--info">
    <x-icon name="briefcase" size="16" />
    <span>Kamu belum terdaftar di jadwal magang/PKL. Jadwal diatur oleh admin di aplikasi Absensi.</span>
  </div>
@else
  <div class="form-actions">
    @if ($hariIniMagang)
      <a class="btn btn--primary" href="{{ route('siswa.jurnal.form', ['tanggal' => today()->toDateString()]) }}">
        <x-icon name="edit" size="16" /> Isi Jurnal Hari Ini
      </a>
    @endif
    <a class="btn btn--success" href="{{ route('siswa.jurnal.cetak') }}" target="_blank" rel="noopener">
      <x-icon name="printer" size="16" /> Cetak Jurnal
    </a>
  </div>

  <div class="grid-2">
    @foreach ($jadwalList as $jadwal)
      <section class="card">
        <div class="card__head">
          <h2><x-icon name="briefcase" size="18" /> {{ $jadwal->tempat }}</h2>
          @if ($jadwal->sedangBerjalan())
            <span class="chip chip--green">Berjalan</span>
          @elseif (today()->lt($jadwal->tanggal_mulai))
            <span class="chip chip--slate">Belum mulai</span>
          @else
            <span class="chip chip--muted">Selesai</span>
          @endif
        </div>
        <dl class="kv">
          <dt>Periode</dt>
          <dd>{{ $jadwal->tanggal_mulai->translatedFormat('d F Y') }} &ndash; {{ $jadwal->tanggal_selesai->translatedFormat('d F Y') }}</dd>
          <dt>Hari magang</dt>
          <dd>{{ $jadwal->namaHari() }}</dd>
          <dt>Pembimbing</dt>
          <dd>{{ $jadwal->pembimbing ?: '-' }}</dd>
          @if ($jadwal->keterangan)
            <dt>Keterangan</dt>
            <dd>{{ $jadwal->keterangan }}</dd>
          @endif
        </dl>
      </section>
    @endforeach
  </div>

  <section class="card">
    <div class="card__head">
      <h2>Hari Magang s.d. Hari Ini</h2>
      <span class="muted">Terisi <strong>{{ $jumlahTerisi }}</strong> dari <strong>{{ $hariMagang->count() }}</strong> hari</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Tempat</th>
            <th>Kegiatan</th>
            <th>Status</th>
            <th class="ta-r">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($hariMagang as $hari)
            @php($jurnal = $hari['jurnal'])
            <tr>
              <td class="nowrap">{{ $hari['tanggal']->translatedFormat('l, d M Y') }}</td>
              <td>{{ $hari['jadwal']->tempat }}</td>
              <td class="wrap">{{ $jurnal ? Str::limit($jurnal->kegiatan, 90) : '' }}</td>
              <td>
                @if ($jurnal)
                  <span class="chip chip--{{ $jurnal->warnaStatus() }}">{{ $jurnal->status }}</span>
                @else
                  <span class="chip chip--muted">Belum diisi</span>
                @endif
              </td>
              <td>
                <div class="row-actions">
                  @if ($jurnal)
                    <a class="icon-btn" href="{{ route('siswa.jurnal.show', $jurnal) }}" title="Lihat"><x-icon name="eye" size="15" /></a>
                  @endif
                  @if (! $jurnal || $jurnal->bisaDiubahSiswa())
                    <a class="icon-btn" href="{{ route('siswa.jurnal.form', ['tanggal' => $hari['tanggal']->toDateString()]) }}" title="{{ $jurnal ? 'Ubah' : 'Isi' }}">
                      <x-icon :name="$jurnal ? 'edit' : 'plus'" size="15" />
                    </a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">Belum ada hari magang yang berjalan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endif
@endsection
