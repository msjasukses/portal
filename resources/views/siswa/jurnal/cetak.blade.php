<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Jurnal PKL - {{ $siswa->nama_siswa }}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 24px; }
  h2 { text-align: center; margin: 0 0 2px; font-size: 16px; }
  .sub { text-align: center; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; }
  .info td { padding: 2px 4px; }
  .jurnal th, .jurnal td { border: 1px solid #000; padding: 5px; vertical-align: top; }
  .jurnal th { background: #eee; }
  .center { text-align: center; }
  .pre-line { white-space: pre-line; }
  .ttd { margin-top: 32px; }
  .ttd td { width: 50%; text-align: center; vertical-align: top; }
  .no-print { margin-bottom: 12px; }
  @media print { .no-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="no-print"><button type="button" onclick="window.print()">Cetak</button></div>

<h2>JURNAL KEGIATAN PRAKTIK KERJA LAPANGAN (PKL)</h2>
<div class="sub">{{ $namaSekolah }}</div>

<table class="info" style="margin-bottom:12px;">
  <tr>
    <td style="width:150px;">Nama Siswa</td><td>: {{ $siswa->nama_siswa }}</td>
    <td style="width:150px;">Tempat PKL</td><td>: {{ $jadwal->tempat }}</td>
  </tr>
  <tr>
    <td>NIS / NISN</td><td>: {{ $siswa->nis ?: '-' }} / {{ $siswa->nisn }}</td>
    <td>Pembimbing</td><td>: {{ $jadwal->pembimbing ?: '-' }}</td>
  </tr>
  <tr>
    <td>Kelas</td><td>: {{ $kelas ?? '-' }}</td>
    <td>Periode</td><td>: {{ $jadwal->tanggal_mulai->translatedFormat('d F Y') }} &ndash; {{ $jadwal->tanggal_selesai->translatedFormat('d F Y') }}</td>
  </tr>
</table>

<table class="jurnal">
  <thead>
    <tr>
      <th style="width:30px;">No</th>
      <th style="width:110px;">Hari, Tanggal</th>
      <th style="width:70px;">Jam</th>
      <th>Kegiatan</th>
      <th>Hasil / Kompetensi</th>
      <th style="width:70px;">Paraf / Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($items as $jurnal)
      <tr>
        <td class="center">{{ $loop->iteration }}</td>
        <td>{{ $jurnal->tanggal->translatedFormat('l, d/m/Y') }}</td>
        <td class="center">{{ $jurnal->jam_mulai ? substr($jurnal->jam_mulai, 0, 5).'–'.substr((string) $jurnal->jam_selesai, 0, 5) : '' }}</td>
        <td class="pre-line">{{ $jurnal->kegiatan }}</td>
        <td class="pre-line">{{ $jurnal->hasil }}</td>
        <td class="center">{{ $jurnal->status }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="center">Belum ada jurnal.</td></tr>
    @endforelse
  </tbody>
</table>

<table class="ttd">
  <tr>
    <td>Pembimbing Industri / Sekolah<br><br><br><br><br>( {{ $jadwal->pembimbing ?: '.............................' }} )</td>
    <td>{{ now()->translatedFormat('d F Y') }}<br>Siswa<br><br><br><br>( {{ $siswa->nama_siswa }} )</td>
  </tr>
</table>
</body>
</html>
