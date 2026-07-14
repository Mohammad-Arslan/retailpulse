<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use InvalidArgumentException;

final class NavigationRegistry
{
    /** @var array<string, NavigationSection> */
    private array $sections = [];

    public function registerSection(NavigationSection $section): void
    {
        if (isset($this->sections[$section->id])) {
            throw new InvalidArgumentException("Navigation section [{$section->id}] is already registered.");
        }

        $this->sections[$section->id] = $section;
    }

    /**
     * Append items to an existing section (or create it if missing).
     *
     * @param  list<NavigationItem>  $items
     */
    public function registerItems(string $sectionId, string $labelKey, array $items, int $order = 0): void
    {
        if (! isset($this->sections[$sectionId])) {
            $this->sections[$sectionId] = new NavigationSection($sectionId, $labelKey, $order, $items);

            return;
        }

        $existing = $this->sections[$sectionId];
        $this->sections[$sectionId] = $existing->withItems([...$existing->items, ...$items]);
    }

    /**
     * @return list<NavigationSection>
     */
    public function sections(): array
    {
        $sections = array_values($this->sections);
        usort(
            $sections,
            static fn (NavigationSection $a, NavigationSection $b): int => $a->order <=> $b->order,
        );

        return $sections;
    }

    /**
     * @return list<NavigationItem>
     */
    public function allItems(): array
    {
        $items = [];
        foreach ($this->sections() as $section) {
            foreach ($section->items as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
