<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(static::cacheKey($key));
    }

    public static function getCached(string $key, $default = null, int $ttl = 60)
    {
        return Cache::remember(static::cacheKey($key), $ttl, function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            return $row?->value ?? $default;
        });
    }

    protected static function cacheKey(string $key): string
    {
        return 'settings:'.$key;
    }
}
