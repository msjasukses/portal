<?php

namespace App\Models\Kurikulum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris daftar isi buku digital: bab (level 1) atau sub bab (level 2).
 */
class BukuDigitalBab extends Model
{
    protected $connection = 'kurikulum';

    protected $table = 'buku_digital_bab';

    /** Sub bab di bawah bab ini, urut seperti di buku. */
    public function subBab(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id')->orderBy('urutan');
    }
}
