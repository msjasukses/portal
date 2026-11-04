{{-- Status pengumpulan satu tugas (sama dengan penanda di dashboard siswa Kurikulum). --}}
@if (! $pengumpulan)
  <span class="chip chip--red">Belum dikumpulkan</span>
@elseif ($pengumpulan->sudahDinilai())
  <span class="chip chip--blue">Nilai {{ $pengumpulan->nilai }}</span>
@elseif ($pengumpulan->terlambat)
  <span class="chip chip--amber">Dikumpulkan (terlambat)</span>
@else
  <span class="chip chip--green">Sudah dikumpulkan</span>
@endif
