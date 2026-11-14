<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logo sekolah, dipinjam dari aplikasi Data Center.
 *
 * Logo sekolah hanya punya satu pemilik yang masuk akal: Data Center, tempat
 * operator mengunggahnya sekali lewat Pengaturan Aplikasi. Portal tidak
 * menyimpan salinannya sendiri — salinan berarti sekolah yang mengganti logo
 * harus ingat mengganti di dua tempat, dan yang terlupa akan memajang logo
 * lama entah sampai kapan.
 *
 * Seperti seluruh isi arahan, kegagalan di sini tidak boleh menjatuhkan apa
 * pun: database Data Center yang mati, tabel yang belum ada, atau berkas yang
 * sudah dihapus semuanya berarti satu hal yang sama — tidak ada logo, dan
 * aplikasi memakai logo bawaannya.
 */
class LogoSekolah
{
    /** Jalur logo di dalam disk datacenter, atau null bila tidak ada. */
    public function jalur(): ?string
    {
        $logo = rescue(
            fn () => DB::connection('datacenter')->table('sekolah')->value('logo'),
            null,
            report: false,
        );

        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        // Keberadaan berkasnya diperiksa sekarang, bukan nanti ketika aplikasi
        // sudah terlanjur diberi alamat yang menjawab 404. Logo yang hilang
        // dari disk tetapi tercatat di database bukan hal langka: folder
        // storage kerap tidak ikut saat aplikasi dipindahkan antar server.
        //
        // Dibungkus rescue() juga, bukan hanya querynya. Disk "datacenter"
        // bisa saja belum ada sama sekali — config lama yang masih ter-cache
        // di server sesudah berkasnya diperbarui — dan Storage::disk() atas
        // disk yang tidak dikenal melempar galat. Arahan dipanggil sebelum
        // siswa masuk; ia tidak boleh jatuh karena satu config:clear yang
        // terlewat.
        return rescue(
            fn () => $this->disk()->exists($logo),
            false,
            report: false,
        ) ? $logo : null;
    }

    public function ada(): bool
    {
        return $this->jalur() !== null;
    }

    public function disk(): Filesystem
    {
        return Storage::disk('datacenter');
    }
}
