<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Nama instance di container: tabel settings cukup dibaca sekali per request.
     */
    protected const CACHE_KEY = 'settings.values';

    /**
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        if (! app()->bound(self::CACHE_KEY)) {
            app()->instance(self::CACHE_KEY, static::query()->pluck('value', 'key')->all());
        }

        return app(self::CACHE_KEY);
    }

    /**
     * Nilai pengaturan; nilai kosong dianggap belum diisi sehingga memakai $default.
     */
    public static function get(string $key, string $default = ''): string
    {
        $value = static::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        app()->forgetInstance(self::CACHE_KEY);
    }
}
