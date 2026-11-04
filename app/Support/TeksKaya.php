<?php

namespace App\Support;

/**
 * Menampilkan isian editor teks kaya (TinyMCE) dari aplikasi Kurikulum,
 * mis. instruksi tugas. Aturan penyaringannya sama dengan App\Support\TeksKaya
 * di Kurikulum, dan dijalankan ulang saat tampil karena isinya berasal dari
 * database aplikasi lain.
 */
class TeksKaya
{
    public const TAG_DIIZINKAN = '<p><br><hr><b><strong><i><em><u><s><strike><span><div>'
        .'<ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code>'
        .'<table><thead><tbody><tfoot><tr><th><td><caption><sub><sup><a>';

    /** HTML aman siap tampil; teks polos lama tetap terjaga pergantian barisnya. */
    public static function html(?string $nilai): string
    {
        $nilai = (string) $nilai;

        if (trim($nilai) === '') {
            return '';
        }

        if ($nilai === strip_tags($nilai)) {
            return nl2br(e($nilai));
        }

        $bersih = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $nilai);
        $bersih = strip_tags($bersih, self::TAG_DIIZINKAN);
        // Buang atribut event (onclick, onerror, ...) dan tautan javascript:.
        $bersih = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $bersih);

        return preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*/i', '$1=$2#', $bersih);
    }
}
