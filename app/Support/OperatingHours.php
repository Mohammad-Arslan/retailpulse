<?php

declare(strict_types=1);

namespace App\Support;

final class OperatingHours
{
    private const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    public static function defaults(): array
    {
        $hours = [];

        foreach (self::DAYS as $day) {
            $hours[$day] = [
                'open' => '09:00',
                'close' => '18:00',
                'closed' => in_array($day, ['saturday', 'sunday'], true),
            ];
        }

        return $hours;
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    public static function normalize(?array $input): array
    {
        $defaults = self::defaults();

        if ($input === null) {
            return $defaults;
        }

        foreach (self::DAYS as $day) {
            if (! isset($input[$day]) || ! is_array($input[$day])) {
                continue;
            }

            $dayInput = $input[$day];
            $defaults[$day] = [
                'open' => (string) ($dayInput['open'] ?? $defaults[$day]['open']),
                'close' => (string) ($dayInput['close'] ?? $defaults[$day]['close']),
                'closed' => (bool) ($dayInput['closed'] ?? $defaults[$day]['closed']),
            ];
        }

        return $defaults;
    }
}
