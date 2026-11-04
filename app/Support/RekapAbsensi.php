<?php

namespace App\Support;

use App\Models\Datacenter\Siswa;
use App\Models\Datacenter\TahunAjaran;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Laporan absensi harian seorang siswa dari database aplikasi Absensi.
 *
 * Aturan statusnya disalin dari aplikasi Absensi (config.php: kalenderPeriode,
 * statusTanggal, magangPada, laporanHarian) supaya angka di Portal Siswa sama
 * persis dengan angka di aplikasi Absensi:
 *  - sakit/izin/dinas/cuti tercatat  -> sesuai catatan
 *  - ada jam masuk                   -> hadir, atau terlambat bila lewat batas
 *  - alpha tercatat                  -> alpha
 *  - terjadwal magang                -> magang (kecuali libur khusus/nasional)
 *  - hari libur                      -> libur (tidak dihitung tidak hadir)
 *  - hari sekolah tanpa catatan      -> alpha
 */
class RekapAbsensi
{
    /** Kode kolom absensi_siswa.status. */
    private const KODE_MASUK = 0;

    private const KODE_PULANG = 1;

    private const KODE_KETIDAKHADIRAN = [2 => 'sakit', 3 => 'izin', 4 => 'alpha', 5 => 'dinas', 6 => 'cuti'];

    /** Seluruh status laporan beserta labelnya (urutan sama dengan aplikasi Absensi). */
    public const LABEL = [
        'hadir' => 'Hadir',
        'terlambat' => 'Terlambat',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'dinas' => 'Dinas Luar',
        'cuti' => 'Cuti',
        'magang' => 'Magang',
        'alpha' => 'Alpha',
        'libur' => 'Libur',
    ];

    /** Status yang hanya berlaku untuk guru; disembunyikan dari ringkasan siswa. */
    public const KHUSUS_GURU = ['dinas', 'cuti'];

    public const WARNA = [
        'hadir' => 'green',
        'terlambat' => 'amber',
        'izin' => 'sky',
        'sakit' => 'blue',
        'dinas' => 'slate',
        'cuti' => 'slate',
        'magang' => 'violet',
        'alpha' => 'red',
        'libur' => 'muted',
    ];

    /**
     * Rentang laporan satu bulan, dibatasi hari ini dan rentang tahun ajaran
     * (seperti periode bawaan aplikasi Absensi). Bisa kosong (dari > sampai)
     * bila bulan tersebut belum berjalan.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function periodeBulan(CarbonInterface $bulan, ?TahunAjaran $tahunAjaran): array
    {
        $dari = CarbonImmutable::parse($bulan)->startOfMonth();
        $sampai = CarbonImmutable::parse($bulan)->endOfMonth()->startOfDay()->min(CarbonImmutable::today());

        if ($tahunAjaran?->tanggal_mulai) {
            $dari = $dari->max(CarbonImmutable::parse($tahunAjaran->tanggal_mulai));
        }

        if ($tahunAjaran?->tanggal_selesai) {
            $sampai = $sampai->min(CarbonImmutable::parse($tahunAjaran->tanggal_selesai));
        }

        return [$dari, $sampai];
    }

    /** Rekap kosong dengan urutan status yang baku. */
    public static function rekapKosong(): array
    {
        return array_fill_keys(array_keys(self::LABEL), 0);
    }

    /**
     * @return array{
     *     rows: list<array{tanggal: CarbonImmutable, jam_masuk: ?string, jam_pulang: ?string, status: string, keterangan: ?string, shift: ?string}>,
     *     rekap: array<string, int>
     * }
     */
    public function laporan(Siswa $siswa, ?int $rombelId, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $rekap = self::rekapKosong();
        $dari = CarbonImmutable::parse($dari)->startOfDay();
        $sampai = CarbonImmutable::parse($sampai)->startOfDay();

        if ($dari->gt($sampai)) {
            return ['rows' => [], 'rekap' => $rekap];
        }

        $kunci = $siswa->kunciAbsensi();
        $catatan = $this->catatan($kunci, $dari, $sampai);
        $magang = $this->jadwalMagang($kunci, $dari, $sampai);
        $rows = [];

        foreach ($this->kalender($rombelId, $dari, $sampai) as $tanggal => $info) {
            $catatanHari = $catatan[$tanggal] ?? null;
            ['status' => $status, 'keterangan' => $keterangan] = $this->statusTanggal($info, $catatanHari, $this->magangPada($magang, $tanggal));
            $rekap[$status]++;

            $rows[] = [
                'tanggal' => CarbonImmutable::parse($tanggal),
                'jam_masuk' => $catatanHari['jam_masuk'] ?? null,
                'jam_pulang' => $catatanHari['jam_pulang'] ?? null,
                'status' => $status,
                'keterangan' => $keterangan,
                'shift' => $info['shift'],
            ];
        }

        return ['rows' => $rows, 'rekap' => $rekap];
    }

    private function db(): ConnectionInterface
    {
        return DB::connection('absensi');
    }

    /**
     * Catatan absensi diringkas per tanggal (sama dengan recAbsensi()).
     *
     * @return array<string, array{jam_masuk: ?string, jam_pulang: ?string, status: ?string, keterangan: ?string}>
     */
    private function catatan(string $kunci, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $hasil = [];

        $baris = $this->db()->table('absensi_siswa')
            ->where('nis', $kunci)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('tanggal')->orderBy('status')->orderBy('jam')
            ->get(['tanggal', 'jam', 'status', 'keterangan']);

        foreach ($baris as $catatan) {
            $tanggal = substr((string) $catatan->tanggal, 0, 10);
            $hasil[$tanggal] ??= ['jam_masuk' => null, 'jam_pulang' => null, 'status' => null, 'keterangan' => null];
            $kode = (int) $catatan->status;

            if ($kode === self::KODE_MASUK) {
                $hasil[$tanggal]['jam_masuk'] = $catatan->jam;
            } elseif ($kode === self::KODE_PULANG) {
                $hasil[$tanggal]['jam_pulang'] = $catatan->jam;
            } else {
                $hasil[$tanggal]['status'] = self::KODE_KETIDAKHADIRAN[$kode] ?? null;
            }

            if (($catatan->keterangan ?? '') !== '') {
                $hasil[$tanggal]['keterangan'] = $catatan->keterangan;
            }
        }

        return $hasil;
    }

    /**
     * Info tiap tanggal: libur atau tidak, batas terlambat, nama libur khusus,
     * dan shift kelas (sama dengan kalenderPeriode()). Shift kelas menimpa
     * jadwal umum pada hari yang punya shift.
     *
     * @return array<string, array{libur: bool, batas: string, ket: ?string, shift: ?string}>
     */
    private function kalender(?int $rombelId, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $jadwalHari = $this->db()->table('jadwal_absensi')->where('tipe', 'siswa')->get()
            ->keyBy(fn ($jadwal) => (int) $jadwal->hari);

        $shiftHari = $rombelId === null ? collect() : $this->db()->table('jadwal_shift_kelas as j')
            ->join('shift as s', 's.id', '=', 'j.shift_id')
            ->where('j.rombel_id', $rombelId)
            ->get(['j.hari', 's.nama', 's.jam_masuk', 's.batas_terlambat'])
            ->keyBy(fn ($shift) => (int) $shift->hari);

        $liburKhusus = [];
        $daftarLibur = $this->db()->table('hari_libur')
            ->where('tanggal', '<=', $sampai->toDateString())
            ->whereRaw('COALESCE(tanggal_selesai, tanggal) >= ?', [$dari->toDateString()])
            ->get();

        foreach ($daftarLibur as $libur) {
            $akhir = CarbonImmutable::parse($libur->tanggal_selesai ?: $libur->tanggal);

            for ($hari = CarbonImmutable::parse($libur->tanggal); $hari->lte($akhir); $hari = $hari->addDay()) {
                $liburKhusus[$hari->toDateString()] = $libur->keterangan;
            }
        }

        $kalender = [];

        for ($hari = $dari; $hari->lte($sampai); $hari = $hari->addDay()) {
            $tanggal = $hari->toDateString();
            $jadwal = $jadwalHari->get($hari->dayOfWeekIso);
            $shift = $shiftHari->get($hari->dayOfWeekIso);

            $kalender[$tanggal] = $shift ? [
                'libur' => isset($liburKhusus[$tanggal]),
                'batas' => $shift->batas_terlambat ?: $shift->jam_masuk,
                'ket' => $liburKhusus[$tanggal] ?? null,
                'shift' => $shift->nama,
            ] : [
                // Hari non-sekolah: ditandai libur, tanpa jam masuk terjadwal, atau libur khusus.
                'libur' => ! $jadwal || (int) $jadwal->libur === 1 || empty($jadwal->jam_masuk) || isset($liburKhusus[$tanggal]),
                'batas' => ($jadwal && $jadwal->batas_terlambat) ? $jadwal->batas_terlambat : '07:00:00',
                'ket' => $liburKhusus[$tanggal] ?? null,
                'shift' => null,
            ];
        }

        return $kalender;
    }

    /**
     * @return list<object>
     */
    private function jadwalMagang(string $kunci, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        return $this->db()->table('jadwal_magang')
            ->where('nis', $kunci)
            ->where('tanggal_mulai', '<=', $sampai->toDateString())
            ->where('tanggal_selesai', '>=', $dari->toDateString())
            ->orderBy('tanggal_mulai')
            ->get()->all();
    }

    /**
     * Jadwal magang yang berlaku pada satu tanggal (rentang + hari magang), atau null.
     *
     * @param  list<object>  $jadwal
     */
    private function magangPada(array $jadwal, string $tanggal): ?object
    {
        $hari = (string) CarbonImmutable::parse($tanggal)->dayOfWeekIso;

        foreach ($jadwal as $baris) {
            if ($tanggal >= substr((string) $baris->tanggal_mulai, 0, 10)
                && $tanggal <= substr((string) $baris->tanggal_selesai, 0, 10)
                && in_array($hari, explode(',', (string) $baris->hari), true)) {
                return $baris;
            }
        }

        return null;
    }

    /**
     * @param  array{libur: bool, batas: string, ket: ?string, shift: ?string}  $info
     * @param  array{jam_masuk: ?string, jam_pulang: ?string, status: ?string, keterangan: ?string}|null  $catatan
     * @return array{status: string, keterangan: ?string}
     */
    private function statusTanggal(array $info, ?array $catatan, ?object $magang): array
    {
        $jamMasuk = $catatan['jam_masuk'] ?? null;
        $keterangan = $catatan['keterangan'] ?? null;
        $tercatat = $catatan['status'] ?? null;

        if ($tercatat !== null && $tercatat !== 'alpha') {
            $status = $tercatat;
        } elseif ($jamMasuk) {
            $status = $jamMasuk <= $info['batas'] ? 'hadir' : 'terlambat';
        } elseif ($tercatat === 'alpha') {
            $status = 'alpha';
        } elseif ($magang && ! ($info['libur'] && $info['ket'] !== null)) {
            // Magang tetap berlaku pada hari libur jadwal sekolah (mis. Sabtu)
            // bila dijadwalkan, tetapi tidak pada libur khusus/nasional.
            $status = 'magang';
            $keterangan = $keterangan ?: 'Magang di '.$magang->tempat;
        } elseif ($info['libur']) {
            $status = 'libur';
            $keterangan = $keterangan ?: $info['ket'];
        } else {
            $status = 'alpha';
        }

        return ['status' => $status, 'keterangan' => $keterangan];
    }
}
