<?php

declare(strict_types=1);

namespace App\Services\Navigation;

final readonly class NavigationSection
{
    /**
     * @param  list<NavigationItem>  $items
     */
    public function __construct(
        public string $id,
        public string $labelKey,
        public int $order = 0,
        public array $items = [],
    ) {}

    public function withItems(array $items): self
    {
        return new self($this->id, $this->labelKey, $this->order, $items);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'labelKey' => $this->labelKey,
            'order' => $this->order,
            'items' => array_map(
                static fn (NavigationItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
