<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Nilai pengaturan situs (tabel settings).
     */
    function setting(string $key, string $default = ''): string
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('upload_url')) {
    /**
     * URL publik file unggahan di public/uploads.
     */
    function upload_url(?string $file): ?string
    {
        return $file ? asset('uploads/'.rawurlencode($file)) : null;
    }
}

if (! function_exists('asset_v')) {
    /**
     * URL aset dengan versi berdasarkan waktu ubah file (cache busting).
     */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('initials')) {
    /**
     * Dua huruf awal dari teks, mis. "SMK Negeri" => "SN".
     */
    function initials(string $text): string
    {
        $out = '';
        foreach (array_slice(preg_split('/\s+/', trim($text)) ?: [], 0, 2) as $part) {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}
