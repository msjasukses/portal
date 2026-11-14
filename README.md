# Portal Siswa (Laravel)

Portal mandiri untuk siswa: absensi, agenda mengajar, tugas, materi online, jurnal
PKL, prestasi, dan pelanggaran dalam satu halaman. Dibangun dengan **Laravel 13 +
PHP 8.3**.

Portal ini **tidak punya database isi sendiri**. Semua data siswa dibaca langsung
dari database aplikasi sekolah yang sudah ada (Data Center, Absensi, Kurikulum,
Kesiswaan), sehingga apa yang dilihat siswa selalu sama dengan yang dilihat guru.

> Aplikasi ini adalah pemisahan dari menu Portal Siswa di `../landing-laravel`.
> Controller, model, query, dan tampilannya sama persis; bagian landing page dan
> panel admin tidak ikut dibawa.

## Kebutuhan

- Laragon (PHP 8.3+, MySQL 8, Composer)
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`
- Akses ke database aplikasi sekolah lain (lihat tabel di bawah)

## Database yang dipakai

| Koneksi | Database (bawaan) | Dipakai untuk | Akses |
|---------|-------------------|---------------|-------|
| `mysql` | `portal_siswa` | Sesi, cache, queue, pengaturan tampilan | baca & tulis |
| `datacenter` | `datacenter_v2` | Akun siswa, rombel, guru, mapel, tahun ajaran | baca + penanda login |
| `absensi` | `absensi_sekolah` | Catatan absensi, jadwal magang | baca saja |
| `kurikulum` | `kurikulum` | Agenda, tugas, materi, jurnal PKL | baca & tulis |
| `kesiswaan` | `kesiswaan` | Prestasi, pelanggaran | baca saja |

Yang ditulis portal ke database sekolah hanya dua: **pengumpulan tugas** dan
**jurnal PKL** (keduanya di `kurikulum`), plus penanda login di `datacenter`
(`last_seen_at`, hitungan gagal login). Kalau user database Data Center cuma punya
hak baca, pencatatan login dilewati tanpa menggagalkan login.

Database `portal_siswa` isinya hanya `sessions`, `cache`, `jobs`, dan `settings`.
Tidak ada data siswa yang disalin ke sini.

## Instalasi

Di komputer ini semuanya **sudah terpasang**. Untuk memasang di komputer lain:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database kosong `portal_siswa`, lalu:

```bash
php artisan migrate --seed
```

Terakhir sesuaikan `.env` — bagian yang wajib dicek ada di bagian berikutnya.

## Konfigurasi `.env`

| Kunci | Keterangan |
|-------|------------|
| `DB_DATABASE` | Database portal sendiri (`portal_siswa`) |
| `DATACENTER_DB_*` | Koneksi ke Data Center — tanpa ini login tidak jalan |
| `ABSENSI_DB_*`, `KURIKULUM_DB_*`, `KESISWAAN_DB_*` | Koneksi ke tiga aplikasi lain |
| `LANDING_URL` | Alamat landing page untuk tombol **Halaman Utama**. Kosongkan untuk menyembunyikan tautannya |
| `UJIAN_URL` | Alamat aplikasi ujian (CBT), mis. `https://sekolah.sch.id/cbt`. Diberitahukan ke aplikasi ponsel; kosong = menu Ujian disembunyikan |
| `ABSENSI_URL` | Folder aplikasi Absensi, mis. `http://localhost/presensi` |
| `ABSENSI_BARCODE_KEY` | Harus sama dengan `BARCODE_API_KEY` di `config.local.php` aplikasi Absensi. **Kosong = menu Absen Barcode dimatikan** |
| `KURIKULUM_STORAGE_PATH` | Folder `storage/app/public` aplikasi Kurikulum. Bawaannya `../kurikulum/storage/app/public`, cocok bila kedua aplikasi satu folder `www` |

## Menjalankan

- **Laragon (nginx)**: *Menu → Reload* agar virtual host dibuat otomatis, lalu buka
  <http://portal.test>
- **Tanpa Laragon**: `php artisan serve` lalu buka <http://127.0.0.1:8000>

## Login

Memakai **akun siswa aplikasi Data Center** — NISN + password yang sama seperti di
Data Center. Portal tidak punya tabel akun sendiri dan tidak bisa membuat akun baru.

Aturan keamanan login mengikuti Data Center: akun nonaktif ditolak, dan setelah
beberapa kali gagal akun dikunci sementara. Halaman login juga dibatasi 10
percobaan per menit.

## Menu

| Menu | URL | Isi |
|------|-----|-----|
| **Dashboard** | `/` | Rekap absensi bulan berjalan, 5 agenda/tugas/materi terbaru, ringkasan tugas (belum, dikumpulkan, dinilai) |
| **Absen Barcode** | `/absen` | Absen masuk/pulang dengan memindai QR Layar Tap aplikasi Absensi |
| **Absensi Bulanan** | `/absensi` | Rekap per bulan sepanjang tahun ajaran aktif |
| **Agenda Mengajar** | `/agenda` | Agenda guru di kelas siswa + status kehadiran tiap jam pelajaran, bisa disaring per bulan |
| **Tugas** | `/tugas` | Daftar tugas kelas, pencarian, kumpul jawaban (catatan dan/atau file) |
| **Materi Online** | `/materi` | Materi kelas, pencarian, tombol File & Video |
| **Jurnal PKL / Magang** | `/jurnal-pkl` | Isi jurnal harian, lihat, hapus, dan cetak per jadwal magang |
| **Prestasi** | `/prestasi` | Daftar prestasi + rekap per bidang |
| **Pelanggaran** | `/pelanggaran` | Daftar pelanggaran + total poin |

Tiap halaman hanya menampilkan data milik siswa yang login dan kelasnya sendiri.
Membuka tugas, materi, atau berkas milik kelas/siswa lain ditolak.

### Aturan pengumpulan tugas

Sama dengan menu Tugas siswa di aplikasi Kurikulum:

- Jawaban boleh diubah berkali-kali **selama belum dinilai guru**.
- Pengumpulan lewat batas waktu tetap diterima, tapi ditandai **terlambat**.
- File maksimal 10 MB — `pdf`, `doc(x)`, `xls(x)`, `ppt(x)`, `txt`, `zip`, `rar`,
  `jpg`, `jpeg`, `png`.
- File disimpan di storage Kurikulum (`uploads/pengumpulan_tugas`) supaya guru
  langsung melihatnya di aplikasi Kurikulum.

### Aturan jurnal PKL

- Jurnal hanya bisa diisi pada **tanggal yang terjadwal magang** di aplikasi
  Absensi, dan tidak untuk tanggal yang belum terjadi.
- Jurnal yang **sudah disetujui pembimbing** tidak bisa diubah atau dihapus.
- Setiap penyimpanan (termasuk perbaikan revisi) kembali berstatus *menunggu*.
- Foto kegiatan maksimal 5 MB, disimpan di `uploads/jurnal_pkl` pada storage
  Kurikulum.

### Absen barcode

Layar Tap aplikasi Absensi menampilkan QR yang berganti tiap 2 menit. Portal hanya
memastikan siapa yang login lalu meneruskan token hasil pindaian ke aplikasi
Absensi; **aplikasi Absensi yang menulis absensinya**, jadi hasilnya sama persis
dengan tap kartu RFID, termasuk jeda antar-tap.

Ada dua jalan masuk yang berujung di halaman yang sama:

1. QR dipindai aplikasi kamera HP → membuka `/absen?token=...`
2. QR dipindai dari dalam halaman portal → **butuh HTTPS**, karena browser hanya
   memberi akses kamera pada halaman `https` atau `localhost`

Untuk cara kedua, portal harus diakses lewat `https://` (sertifikat ada di
`C:\laragon\etc\ssl\portal.crt`) atau lewat `http://127.0.0.1:<port>`.

## Bila database sekolah mati

Kalau salah satu database aplikasi sekolah tidak bisa dihubungi, siswa melihat
halaman **"Data belum dapat dimuat"** yang menyebut aplikasi mana yang bermasalah,
bukan error 500. Kesalahannya tetap dicatat di log.

Khusus dashboard, tiap bagian dimuat terpisah — satu aplikasi yang mati tidak
membuat seluruh dashboard gagal.

## API aplikasi ponsel

Portal melayani aplikasi ponsel siswa lewat `/api`. Isinya cermin dari menu di
atas — controller, query, dan aturannya sama, hanya keluarannya JSON. Tidak ada
endpoint pengelolaan di sini, dan tidak boleh ada: guru dan admin tetap memakai
aplikasi masing-masing.

Autentikasinya token bearer dari `POST /api/v1/auth/login` (NISN + password akun
Data Center, aturan kunci dan akun nonaktif sama dengan halaman web). Yang
tersimpan di tabel `siswa_api_token` hanya sidik SHA-256-nya.

| Endpoint | Isi |
|----------|-----|
| `GET /api/ping` | Penanda bahwa alamat ini memang Portal Siswa |
| `GET /api/v1/arahan` | Peta modul yang hidup + alamat aplikasi ujian (`UJIAN_URL`) + versi APK terbaru + alamat logo sekolah |
| `GET /api/v1/logo` | Logo sekolah milik Data Center, tanpa token |
| `POST /api/v1/auth/login` | Masuk, membalas token |
| `GET /api/v1/auth/saya`, `POST /api/v1/auth/logout` | Identitas, keluar |
| `GET /api/v1/dashboard` | Ringkasan layar pertama |
| `GET /api/v1/absensi` | Rekap bulanan (`?bulan=Y-m`) |
| `GET\|POST /api/v1/absen` | Keadaan hari ini, lalu absen dengan token pindaian |
| `GET /api/v1/agenda`, `/materi`, `/tugas`, `/jurnal-pkl`, `/prestasi`, `/pelanggaran` | Daftar per modul |
| `POST /api/v1/tugas/{tugas}` | Kumpul jawaban (catatan dan/atau file) |
| `POST /api/v1/jurnal-pkl`, `DELETE /api/v1/jurnal-pkl/{jurnal}` | Isi dan hapus jurnal |
| `GET /api/v1/jurnal-pkl/cetak` | Lembar jurnal siap cetak (HTML), `?jadwal_id=` opsional |
| `GET /api/v1/katalog-buku`, `/katalog-buku/{buku}` | Katalog buku digital: pencarian, penyaring, rincian + daftar isi |
| `GET /api/v1/modul-ajar/{modul}` | Modul ajar yang dipilih guru pada agenda; tanpa bagian asesmen |
| `GET /api/v1/berkas/...` | Unduh berkas dari storage Kurikulum |

**Satu alamat saja.** `GET /api/v1/arahan` ada supaya operator cukup mengetik IP
atau domain portal — tanpa subfolder. Aplikasi menanyakan peta modulnya ke akar
alamat itu, termasuk di mana ujian berada, jadi menambah atau mematikan satu
menu jadi urusan server dan bukan alasan membangun ulang APK.

**Aturan ujian tidak lewat sini.** Portal hanya memberitahukan alamat aplikasi
ujian; soal, jawaban, penyematan layar, dan batas pelanggaran tetap sepenuhnya
milik aplikasi ujian.

**Kunci absensi tetap di server.** Aplikasi ponsel mengirimkan hasil pindaian QR,
portal yang memanggil `barcode_api.php` memakai `ABSENSI_BARCODE_KEY`. Kunci itu
tidak pernah ikut terbungkus di dalam APK.

Database aplikasi sekolah yang mati dibalas `503` berisi JSON — bukan halaman
blade, yang tidak bisa dibaca aplikasi ponsel.
## Struktur penting

```
app/
├── Http/Controllers/Siswa/
│   ├── AuthController.php           login NISN (akun Data Center)
│   ├── DashboardController.php
│   ├── AbsenBarcodeController.php   absen via QR Layar Tap
│   ├── AbsensiController.php        rekap bulanan
│   ├── AgendaController.php
│   ├── TugasController.php          daftar + pengumpulan
│   ├── MateriController.php
│   ├── JurnalPklController.php      isi / lihat / hapus / cetak
│   ├── PrestasiController.php
│   ├── PelanggaranController.php
│   └── BerkasController.php         buka file dari storage Kurikulum
├── Models/
│   ├── Datacenter/                  Siswa, Rombel, SiswaRombel, Guru,
│   │                                MataPelajaran, TahunAjaran
│   ├── Absensi/JadwalMagang.php
│   ├── Kurikulum/                   AgendaMengajar, Tugas, PengumpulanTugas,
│   │                                MateriOnline, JurnalPkl, Pengguna
│   ├── Kesiswaan/                   Prestasi, Pelanggaran, DataPelanggaran,
│   │                                JenisPelanggaran
│   └── Setting.php                  nama & logo sekolah
├── Support/
│   ├── AbsensiBarcode.php           klien API barcode aplikasi Absensi
│   ├── RekapAbsensi.php             perhitungan rekap absensi
│   ├── TeksKaya.php                 penyaring HTML editor teks kaya Kurikulum
│   └── Icon.php                     ikon SVG
└── helpers.php                      setting(), upload_url(), asset_v(), initials()
config/
├── database.php                     5 koneksi (portal + 4 aplikasi sekolah)
├── auth.php                         guard tunggal "siswa"
├── filesystems.php                  disk "kurikulum"
└── portal.php                       LANDING_URL
resources/views/
├── layouts/siswa.blade.php          sidebar + topbar
├── siswa/                           halaman tiap menu
└── partials/alerts.blade.php
public/
├── css/portal.css
└── js/portal.js, js/siswa-absen.js  pemindai QR di halaman absen
```

## Modul ajar pada agenda mengajar

Saat mengisi agenda mengajar di aplikasi Kurikulum, guru boleh memilih satu
modul ajar untuk jam itu (`agenda_mengajar.modul_ajar_id`). Siswa bisa
membukanya dari kartu agenda di aplikasi ponsel.

**Yang menentukan hak baca adalah agendanya, bukan modulnya.** Sebuah modul
boleh dibaca bila ia dipakai pada setidaknya satu agenda di kelas siswa itu,
dan agenda itu tidak ditolak admin saat divalidasi — aturan yang sama dengan
daftar agendanya sendiri. Modul di luar itu dijawab **404, bukan 403**: pesan
"Anda tidak berhak" tetap memberi tahu bahwa modul bernomor itu ada, dan nomor
bisa dicoba satu per satu.

**Bagian asesmen tidak pernah dikirim.** `asesmen_diagnostik`,
`asesmen_formatif`, `asesmen_sumatif`, dan `lkpd_asesmen` adalah tempat guru
menuliskan kisi-kisi, soal, rubrik, dan kerap kunci jawabannya. Membukanya
kepada siswa sebelum penilaian berlangsung bukan keterbukaan — itu
membatalkan penilaiannya. Daftar yang boleh dan yang ditahan ada
bersebelahan di `App\Models\Kurikulum\ModulAjar` (`BAGIAN_SISWA` dan
`BAGIAN_DITAHAN`), supaya keputusannya terlihat oleh siapa pun yang membaca
kodenya dan bukan tersembunyi sebagai kolom yang kebetulan tidak ikut disebut.

Yang dikirim: tujuan dan capaian pembelajaran, alur tujuan, pertanyaan
pemantik, model pembelajaran, target peserta didik, kegiatan pembelajaran
beserta pendahuluan/inti/penutup, dan LKPD selain bagian asesmennya. Bagian
yang kosong tidak ikut — modul yang disusun lewat Generator kerap hanya
mengisi separuhnya.

**Modul unggahan.** Guru yang menyusun modulnya di luar aplikasi mengunggah
satu berkas, dan bagian-bagiannya kosong. Berkas itulah isinya, dialirkan
lewat `GET /api/v1/berkas/modul-ajar/{modul}` dengan pemeriksaan hak yang
sama. Tanpa itu, menu ini akan terasa rusak di sekolah yang gurunya terbiasa
mengunggah.

## Pembaruan APK aplikasi ponsel

Aplikasi siswa dibagikan sebagai APK, di luar Play Store, jadi tidak ada
mekanisme pembaruan yang disediakan toko. Portal yang mengambil peran itu:
`GET /api/v1/arahan` menyisipkan versi APK terbaru, dan aplikasi mengunduh
lalu menyerahkannya ke pemasang Android tanpa siswa meninggalkan aplikasi.

Catatannya tinggal di `public/rilis/terkini.json`, bersebelahan dengan berkas
APK-nya sendiri. Sebuah rilis adalah peristiwa mengirim berkas, jadi catatannya
bepergian bersama berkas itu — mengunggah APK lalu lupa menyunting satu baris
di `.env` di tempat lain adalah kesalahan yang menunggu terjadi, dan
kegagalannya diam.

```json
{
  "kode_versi": 7,
  "versi": "1.2.0",
  "wajib_minimal": 0,
  "terbit_pada": "2026-11-20",
  "catatan": "Katalog buku digital.",
  "apk": {
    "arm64-v8a": { "url": "rilis/cbt-siswa-1.2.0-arm64-v8a.apk", "ukuran": 27262976, "sha256": "..." },
    "universal": { "url": "rilis/cbt-siswa-1.2.0.apk", "ukuran": 79930262, "sha256": "..." }
  }
}
```

Beberapa keputusan yang menentukan bentuknya:

- **Alamat `url` boleh relatif,** dan portal melengkapinya dengan alamat
  peladen yang sedang menjawab. Satu `terkini.json` yang sama karena itu
  bekerja di jaringan sekolah maupun di VPS tanpa disunting sebaris pun.
- **APK disajikan Apache sebagai berkas statis,** bukan dialirkan PHP. 26 MB
  lewat `StreamedResponse` dikali satu kelas yang menekan Perbarui bersamaan
  adalah cara cepat menghabiskan batas proses hosting bersama — dan Apache
  mendukung HTTP Range di sana, sehingga unduhan yang terputus bisa
  dilanjutkan tanpa satu baris kode pun. Aturannya ada di
  `public/rilis/.htaccess`.
- **Manifes separuh jadi ditolak di server.** `App\Support\RilisAplikasi`
  membuang keterangan berkas tanpa `sha256` 64 huruf, tanpa ukuran, atau tanpa
  alamat. Tombol Perbarui yang menuju ke tempat yang tidak ada dibaca siswa
  sebagai aplikasi yang rusak, dan itu lebih buruk daripada pembaruan yang
  tidak pernah ditawarkan.
- **Kegagalannya selalu berarti "tidak ada pembaruan".** Berkas yang hilang,
  rusak, atau tidak terbaca tidak boleh menjatuhkan `arahan` — endpoint itu
  dipanggil sebelum siswa masuk.
- **`wajib_minimal`** memaksa pembaruan bagi versi di bawahnya: layar penuh
  tanpa jalan masuk. Nol berarti tidak ada yang dipaksa, dan itu nilai
  bawaannya — memaksa seluruh sekolah memperbarui pantas diputuskan dengan
  sadar.

Langkah pemasangan dan rilisnya ada di `PASANG-PEMBARUAN-DI-VPS.md` pada
repositori aplikasi ponsel, beserta skrip `rilis.ps1` yang membangun,
menghitung sidik jari, dan menulis manifesnya sekaligus.

## Logo sekolah

Layar masuk aplikasi ponsel memajang logo sekolah, bukan logo CyberEdu. Logo
itu tidak disimpan portal: pemiliknya aplikasi **Data Center**, tempat operator
mengunggahnya sekali lewat Pengaturan Aplikasi → Logo Sekolah. Portal membacanya
dari tabel `sekolah` di database Data Center dan menyajikan berkasnya lewat
`GET /api/v1/logo`.

Disajikan lewat portal, bukan ditunjuk ke alamat Data Center, karena Data Center
tidak selalu terbuka ke luar — dan aplikasi sebaiknya hanya perlu memercayai
satu alamat. Berkasnya dibaca dari disk `datacenter`, yang mencari sendiri
folder `../datacenter` atau `../datacenter-msja`; `DATACENTER_STORAGE_PATH`
memaksanya bila pemasangan Anda menaruhnya di tempat lain.

Memasangnya menuntut `php artisan config:clear` sekali, karena
`config/filesystems.php` bertambah satu disk. Melewatkannya tidak menjatuhkan
apa pun — disk yang belum dikenal diperlakukan sama dengan logo yang tidak ada
— tetapi logonya tidak akan muncul sampai config lamanya dibuang.

Sekolah yang belum mengunggah logo membuat `logo_url` pada arahan bernilai
`null`, dan aplikasi memakai logo bawaannya. Begitu pula bila barisnya ada di
database tetapi berkasnya hilang dari disk — keadaan yang lazim setelah
aplikasi dipindah antar server. Keduanya sah, bukan galat, dan tidak satu pun
menjatuhkan arahan.

## Pengujian

```bash
php artisan test
```

105 feature test mencakup login, seluruh menu, dan API aplikasi ponsel. Test memakai SQLite in-memory
untuk kelima koneksi, jadi **tidak menyentuh database sekolah** sama sekali.

Sebelum menyimpan perubahan PHP:

```bash
vendor/bin/pint
```

## Catatan keamanan

- Semua form memakai token CSRF; login dibatasi 10 percobaan per menit dan absen
  barcode 20 per menit.
- API aplikasi ponsel memakai token bearer, bukan sesi: `login` dibatasi 20
  percobaan per menit dan `absen` 20 per menit. Token yang kedaluwarsa, atau yang
  pemiliknya dinonaktifkan di Data Center, langsung dihapus saat dipakai.
- Berkas lewat API selalu dikirim sebagai unduhan, tidak pernah ditampilkan
  langsung — aplikasi yang membuka filenya sendiri tidak menjalankan isinya di
  dalam portal.
- Siswa hanya bisa membuka data kelas dan miliknya sendiri; selebihnya ditolak
  403/404.
- Berkas dari storage Kurikulum: hanya PDF dan gambar yang tampil langsung di
  browser, jenis lain selalu diunduh (`X-Content-Type-Options: nosniff`) agar HTML
  unggahan tidak berjalan di halaman portal.
- Saat dipasang di hosting: set `APP_ENV=production` dan `APP_DEBUG=false`.
- Sebaiknya pakai user MySQL **read-only** untuk koneksi `absensi` dan `kesiswaan`.
