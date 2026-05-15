<?php

declare(strict_types=1);

namespace App\Support;

final class VariantMatrix
{
    /**
     * Build cartesian combinations from attribute definitions.
     *
     * @param  list<array{name: string, options: list<string>}>  $attributeSets
     * @return list<array<string, string>>
     */
    public static function combinations(array $attributeSets): array
    {
        $sets = array_values(array_filter(
            $attributeSets,
            fn (array $set) => ! empty($set['name']) && ! empty($set['options']),
        ));

        if ($sets === []) {
            return [[]];
        }

        $result = [[]];

        foreach ($sets as $set) {
            $options = array_values(array_unique(array_map(
                static fn (string $option) => trim($option),
                $set['options'],
            )));
            $options = array_values(array_filter($options, static fn (string $o) => $o !== ''));

            if ($options === []) {
                continue;
            }

            $next = [];

            foreach ($result as $combo) {
                foreach ($options as $option) {
                    $next[] = array_merge($combo, [$set['name'] => $option]);
                }
            }

            $result = $next;
        }

        return $result === [] ? [[]] : $result;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public static function label(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        return collect($attributes)->values()->implode(' / ');
    }
}
