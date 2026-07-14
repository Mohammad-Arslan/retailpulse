<?php

declare(strict_types=1);

namespace App\Services\Search\Contracts;

final readonly class SearchResult
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public string $id,
        public string $provider,
        public string $category,
        public string $title,
        public ?string $subtitle = null,
        public array $meta = [],
        public ?string $url = null,
        public ?string $routeName = null,
        public array $routeParams = [],
        public string $icon = 'search',
        public float $score = 0.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $href = $this->url;
        if ($href === null && $this->routeName !== null) {
            try {
                $href = route($this->routeName, $this->routeParams);
            } catch (\Throwable) {
                $href = null;
            }
        }

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'category' => $this->category,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'meta' => $this->meta,
            'href' => $href,
            'icon' => $this->icon,
            'score' => $this->score,
        ];
    }
}
