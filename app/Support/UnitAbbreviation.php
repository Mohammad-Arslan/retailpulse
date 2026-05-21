<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Unit;

final class UnitAbbreviation
{
    public static function fromName(string $name, int $maxLength = 16): string
    {
        $cleaned = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($cleaned === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $cleaned) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));

        if (count($words) >= 2) {
            $abbr = '';

            foreach ($words as $word) {
                if (preg_match('/[a-zA-Z0-9]/', $word, $matches) === 1) {
                    $abbr .= strtoupper($matches[0]);
                }
            }
        } else {
            $word = preg_replace('/[^a-zA-Z0-9]/u', '', $words[0]) ?? '';
            $abbr = strlen($word) <= 6 ? $word : substr($word, 0, 6);
        }

        return strtoupper(substr($abbr, 0, $maxLength));
    }

    public static function forModel(Unit $unit, string $name): string
    {
        $base = self::fromName($name);
        $base = $base !== '' ? $base : 'U';

        $abbreviation = $base;
        $counter = 2;

        while (
            Unit::query()
                ->where('abbreviation', $abbreviation)
                ->when($unit->exists, fn ($query) => $query->whereKeyNot($unit->getKey()))
                ->exists()
        ) {
            $suffix = (string) $counter;
            $abbreviation = substr($base, 0, max(1, 16 - strlen($suffix))).$suffix;
            $counter++;
        }

        return $abbreviation;
    }
}
