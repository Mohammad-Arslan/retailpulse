<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings\SettingGroupRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'group',
    'key',
    'value',
    'type',
    'updated_by',
])]
class SystemSetting extends Model
{
    protected $table = 'system_settings';

    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $cacheKey = self::cacheKey($group, $key);

        return Cache::remember($cacheKey, 3600, function () use ($group, $key, $default) {
            $setting = self::query()->where('group', $group)->where('key', $key)->first();

            if ($setting === null) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    public static function set(string $group, string $key, mixed $value, string $type = 'string'): void
    {
        $stored = match ($type) {
            'encrypted' => $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : '',
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };

        self::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $stored,
                'type' => $type,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ],
        );

        Cache::forget(self::cacheKey($group, $key));
    }

    public static function cacheKey(string $group, string $key): string
    {
        return "system_settings:{$group}:{$key}";
    }

    public static function flushGroupCache(string $group): void
    {
        foreach (SettingGroupRegistry::fields($group) as $fieldKey => $_field) {
            Cache::forget(self::cacheKey($group, $fieldKey));
        }
    }

    /**
     * @param  array<string, mixed>  $entries
     */
    public static function setMany(string $group, array $entries): void
    {
        foreach ($entries as $key => $entry) {
            if (is_array($entry)) {
                self::set($group, $key, $entry['value'] ?? null, $entry['type'] ?? 'string');
            } else {
                self::set($group, $key, $entry);
            }
        }
    }

    public static function getEncrypted(string $group, string $key): ?string
    {
        $value = self::get($group, $key);

        if ($value === null || $value === '') {
            return null;
        }

        $setting = self::query()->where('group', $group)->where('key', $key)->first();

        if ($setting?->type !== 'encrypted' || $setting->value === '') {
            return is_string($value) ? $value : null;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'encrypted' => $value !== '' ? self::decryptStored($value) : null,
            default => $value,
        };
    }

    private static function decryptStored(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
