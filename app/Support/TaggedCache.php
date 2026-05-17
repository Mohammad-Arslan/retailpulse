<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;

final class TaggedCache
{
    /**
     * @param  list<string>  $tags
     */
    public static function flush(array $tags): void
    {
        if (! self::supportsTags()) {
            return;
        }

        Cache::tags($tags)->flush();
    }

    /**
     * @param  list<string>  $tags
     */
    public static function remember(string $key, int|\DateInterval $ttl, callable $callback, array $tags = []): mixed
    {
        if ($tags !== [] && self::supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public static function supportsTags(): bool
    {
        return method_exists(self::store(), 'tags');
    }

    /**
     * @param  list<string>  $tags
     */
    public static function tagged(array $tags): Repository
    {
        if (self::supportsTags()) {
            return Cache::tags($tags);
        }

        return Cache::store();
    }

    private static function store(): Store
    {
        return Cache::store()->getStore();
    }
}
