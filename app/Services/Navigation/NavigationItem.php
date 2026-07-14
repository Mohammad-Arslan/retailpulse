<?php

declare(strict_types=1);

namespace App\Services\Navigation;

final readonly class NavigationItem
{
    /**
     * @param  list<string>  $permissionsAny
     * @param  list<string>  $keywords
     * @param  list<string>|null  $activeRoutes
     */
    public function __construct(
        public string $id,
        public string $titleKey,
        public string $route,
        public string $icon,
        public string $group,
        public int $order = 0,
        public ?string $permission = null,
        public array $permissionsAny = [],
        public array $keywords = [],
        public ?string $module = null,
        public ?string $routePattern = null,
        public ?array $activeRoutes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'labelKey' => $this->titleKey,
            'href' => $this->route,
            'routeName' => $this->routePattern ?? $this->route,
            'icon' => $this->icon,
            'group' => $this->group,
            'order' => $this->order,
            'permission' => $this->permission,
            'permissionsAny' => $this->permissionsAny,
            'keywords' => $this->keywords,
            'module' => $this->module,
            'activeRoutes' => $this->activeRoutes,
        ];
    }
}
