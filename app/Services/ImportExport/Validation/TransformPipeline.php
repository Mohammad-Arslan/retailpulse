<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Str;

final class TransformPipeline
{
    /** @var array<string, Closure(mixed): mixed> */
    private static array $transforms = [];

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$transforms = [
            'trim' => fn (mixed $v) => is_string($v) ? trim($v) : $v,
            'lowercase' => fn (mixed $v) => is_string($v) ? mb_strtolower($v) : $v,
            'uppercase' => fn (mixed $v) => is_string($v) ? mb_strtoupper($v) : $v,
            'cast_int' => fn (mixed $v) => $v === null || $v === '' ? null : (int) $v,
            'cast_float' => fn (mixed $v) => $v === null || $v === '' ? null : (float) $v,
            'cast_bool' => fn (mixed $v) => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'slug' => fn (mixed $v) => is_string($v) ? Str::slug($v) : $v,
            'strip_spaces' => fn (mixed $v) => is_string($v) ? preg_replace('/\s+/', '', $v) : $v,
            'nullify_empty' => fn (mixed $v) => $v === '' ? null : $v,
            'date_normalize' => function (mixed $v) {
                if ($v === null || $v === '') {
                    return null;
                }

                return Carbon::parse((string) $v)->format('Y-m-d');
            },
        ];

        self::$booted = true;
    }

    /**
     * @param  string|array<string, mixed>  $definition
     */
    public static function apply(string|array $definition, mixed $value): mixed
    {
        self::boot();

        $name = is_string($definition) ? $definition : ($definition['name'] ?? '');

        if ($name === '' || ! isset(self::$transforms[$name])) {
            return $value;
        }

        return (self::$transforms[$name])($value);
    }

    public static function register(string $name, Closure $transform, string $label): void
    {
        self::boot();
        self::$transforms[$name] = $transform;
        self::$meta[$name] = $label;
    }

    /** @var array<string, string> */
    private static array $meta = [
        'trim' => 'Trim whitespace',
        'lowercase' => 'Lowercase',
        'uppercase' => 'Uppercase',
        'cast_int' => 'Cast to integer',
        'cast_float' => 'Cast to float',
        'cast_bool' => 'Cast to boolean',
        'slug' => 'Slug',
        'strip_spaces' => 'Strip all spaces',
        'nullify_empty' => 'Nullify empty strings',
        'date_normalize' => 'Normalize date (Y-m-d)',
    ];

    /**
     * @return list<array{name: string, label: string}>
     */
    public static function allMeta(): array
    {
        self::boot();

        return collect(self::$meta)
            ->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])
            ->values()
            ->all();
    }
}
