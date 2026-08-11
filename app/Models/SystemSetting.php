<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Get a setting by key with fallback.
     */
    public static function get(string $key, $default = null): ?string
    {
        return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget("system_setting_{$key}");
    }

    /**
     * Get all settings as key => value array.
     */
    public static function allMap(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}
