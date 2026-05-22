<?php

declare(strict_types=1);

namespace App\Support\Settings;

use InvalidArgumentException;

final class SettingGroupRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = config('settings.groups', []);

        return $groups;
    }

    public static function exists(string $group): bool
    {
        return array_key_exists($group, self::all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $group): array
    {
        if (! self::exists($group)) {
            throw new InvalidArgumentException("Unknown settings group: {$group}");
        }

        return self::all()[$group];
    }

    public static function permission(string $group): string
    {
        return (string) self::get($group)['permission'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(string $group): array
    {
        /** @var array<string, array<string, mixed>> */
        return self::get($group)['fields'] ?? [];
    }

    public static function testsConnection(string $group): bool
    {
        return (bool) (self::get($group)['test_connection'] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
