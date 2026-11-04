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

## Pengujian

```bash
php artisan test
```

65 feature test mencakup login dan seluruh menu. Test memakai SQLite in-memory
untuk kelima koneksi, jadi **tidak menyentuh database sekolah** sama sekali.

Sebelum menyimpan perubahan PHP:

```bash
vendor/bin/pint
```

## Catatan keamanan

- Semua form memakai token CSRF; login dibatasi 10 percobaan per menit dan absen
  barcode 20 per menit.
- Siswa hanya bisa membuka data kelas dan miliknya sendiri; selebihnya ditolak
  403/404.
- Berkas dari storage Kurikulum: hanya PDF dan gambar yang tampil langsung di
  browser, jenis lain selalu diunduh (`X-Content-Type-Options: nosniff`) agar HTML
  unggahan tidak berjalan di halaman portal.
- Saat dipasang di hosting: set `APP_ENV=production` dan `APP_DEBUG=false`.
- Sebaiknya pakai user MySQL **read-only** untuk koneksi `absensi` dan `kesiswaan`.
