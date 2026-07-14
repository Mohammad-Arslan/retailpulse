<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class AccessControlLabels
{
    public static function forRole(string $name): string
    {
        return Str::headline(str_replace(['_', '.'], ' ', $name));
    }

    public static function forPermission(string $name, ?string $description = null): string
    {
        if (is_string($description) && trim($description) !== '') {
            return Str::headline($description);
        }

        $parts = explode('.', $name);

        if (count($parts) > 1) {
            array_shift($parts);
        }

        return Str::headline(implode(' ', $parts));
    }

    public static function forGroup(string $group): string
    {
        return Str::headline(str_replace(['_', '-'], ' ', $group));
    }
}
