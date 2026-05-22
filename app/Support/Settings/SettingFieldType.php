<?php

declare(strict_types=1);

namespace App\Support\Settings;

final class SettingFieldType
{
    public static function storageType(string $uiType): string
    {
        return match ($uiType) {
            'integer' => 'integer',
            'boolean' => 'boolean',
            'encrypted' => 'encrypted',
            'json' => 'json',
            default => 'string',
        };
    }
}
