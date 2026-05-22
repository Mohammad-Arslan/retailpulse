<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ListPagination
{
    public const DEFAULT = 15;

    /** @var list<int> */
    public const OPTIONS = [10, 15, 25, 50, 100];

    public static function resolve(mixed $value, int $default = self::DEFAULT): int
    {
        $requested = (int) $value;

        return in_array($requested, self::OPTIONS, true) ? $requested : $default;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function filters(Request $request, array $keys, int $default = self::DEFAULT): array
    {
        $filters = $request->only([...$keys, 'per_page']);
        $filters['per_page'] = self::resolve($filters['per_page'] ?? null, $default);

        return $filters;
    }
}
