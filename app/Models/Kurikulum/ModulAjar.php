<?php

namespace App\Models\Kurikulum;

use App\Models\Datacenter\MataPelajaran;
use Database\Factories\Kurikulum\ModulAjarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modul ajar yang dipilih guru saat mengisi agenda mengajar — aplikasi
 * Kurikulum. Baca-saja: yang menyusun dan mengubah modul tetap guru, di
 * aplikasinya sendiri.
 *
 * Sebuah modul datang dalam dua bentuk. Yang disusun lewat Generator Modul
 * Ajar mengisi bagian-bagiannya satu per satu sebagai teks kaya; yang
 * diunggah guru hanya punya satu berkas dan bagian-bagiannya kosong. Keduanya
 * sah, dan siswa harus bisa membaca keduanya — kalau tidak, menu ini terasa
 * rusak di sekolah yang gurunya terbiasa mengunggah.
 */
class ModulAjar extends Model
{
    /** @use HasFactory<ModulAjarFactory> */
    use HasFactory;

    /**
     * Bagian isi modul yang boleh dibaca siswa, urut seperti di modulnya.
     *
     * Tujuan pembelajaran didahulukan dengan sengaja: itu satu-satunya bagian
     * yang menjawab pertanyaan yang benar-benar dimiliki siswa — "setelah
     * pelajaran ini saya harus bisa apa".
     *
     * @var array<string, string>
     */
    public const BAGIAN_SISWA = [
        'tujuan_pembelajaran' => 'Tujuan Pembelajaran',
        'capaian_pembelajaran' => 'Capaian Pembelajaran',
        'alur_tujuan_pembelajaran' => 'Alur Tujuan Pembelajaran',
        'pertanyaan_pemantik' => 'Pertanyaan Pemantik',
        'model_pembelajaran' => 'Model Pembelajaran',
        'target_peserta_didik' => 'Target Peserta Didik',
        'kegiatan_pembelajaran' => 'Kegiatan Pembelajaran',
        'pendahuluan' => 'Pendahuluan',
        'kegiatan_inti' => 'Kegiatan Inti',
        'penutup' => 'Penutup',
        'lkpd_tujuan' => 'LKPD · Tujuan',
        'lkpd_petunjuk' => 'LKPD · Petunjuk',
        'lkpd_kegiatan' => 'LKPD · Kegiatan',
        'lkpd_refleksi' => 'LKPD · Refleksi',
    ];

    /**
     * Bagian yang sengaja TIDAK pernah dikirim ke aplikasi siswa.
     *
     * Ketiga bagian asesmen dan asesmen LKPD adalah tempat guru menuliskan
     * instrumen penilaian — kisi-kisi, soal, rubrik, dan kerap kunci
     * jawabannya. Membukanya kepada siswa sebelum penilaian berlangsung bukan
     * keterbukaan, itu membatalkan penilaiannya. Daftar ini ada di sini,
     * bersebelahan dengan daftar di atas, supaya keputusannya terlihat oleh
     * siapa pun yang membaca kode ini — bukan tersembunyi sebagai kolom yang
     * kebetulan tidak ikut disebut.
     *
     * @var list<string>
     */
    public const BAGIAN_DITAHAN = [
        'asesmen_diagnostik',
        'asesmen_formatif',
        'asesmen_sumatif',
        'lkpd_asesmen',
    ];

    protected $connection = 'kurikulum';

    protected $table = 'modul_ajar';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_ukuran' => 'integer',
            'jumlah_jam' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    /** Modul ini berasal dari berkas yang diunggah guru, bukan Generator. */
    public function dariUnggahan(): bool
    {
        return filled($this->file);
    }

    /** Berkas unggahannya masih benar-benar ada di penyimpanan Kurikulum. */
    public function fileTersedia(): bool
    {
        return filled($this->file) && Storage::disk('kurikulum')->exists($this->file);
    }

    /** Ukuran berkas yang enak dibaca, mis. "1,4 MB". Koma desimal. */
    public function ukuranFileManusiawi(): ?string
    {
        if (! $this->file_ukuran) {
            return null;
        }

        if ($this->file_ukuran < 1024) {
            return $this->file_ukuran.' B';
        }

        $mb = $this->file_ukuran / 1048576;

        return $mb < 1
            ? number_format($this->file_ukuran / 1024, 0, ',', '.').' KB'
            : number_format($mb, 1, ',', '.').' MB';
    }

    /**
     * Siswa ini berhak membaca modul ini?
     *
     * Haknya tidak datang dari modulnya, melainkan dari agenda: modul boleh
     * dibaca bila ia dipilih pada setidaknya satu agenda mengajar di kelas
     * siswa itu, dan agenda itu tidak ditolak admin saat divalidasi. Aturan
     * yang sama dengan daftar agendanya sendiri.
     *
     * Tanpa penjagaan ini, nomor modul pada alamat bisa diganti satu per satu
     * dan seluruh modul sekolah — termasuk kelas dan mata pelajaran yang tidak
     * pernah diikuti siswa itu — bisa dibaca siapa saja yang bisa masuk.
     *
     * @param  list<int>  $kelasId
     */
    public function terpakaiDiKelas(array $kelasId): bool
    {
        if ($kelasId === []) {
            return false;
        }

        return AgendaMengajar::query()
            ->where('modul_ajar_id', $this->id)
            ->whereIn('kelas_id', $kelasId)
            ->where('status', '!=', AgendaMengajar::STATUS_DITOLAK)
            ->exists();
    }
}
