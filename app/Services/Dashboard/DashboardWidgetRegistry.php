<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Services\Dashboard\Contracts\DashboardWidget;
use InvalidArgumentException;

final class DashboardWidgetRegistry
{
    /** @var array<string, DashboardWidget> */
    private array $widgets = [];

    public function register(DashboardWidget $widget): void
    {
        $id = $widget->id();

        if (isset($this->widgets[$id])) {
            throw new InvalidArgumentException("Dashboard widget [{$id}] is already registered.");
        }

        $this->widgets[$id] = $widget;
    }

    /**
     * @return list<DashboardWidget>
     */
    public function all(): array
    {
        $widgets = array_values($this->widgets);

        usort(
            $widgets,
            fn (DashboardWidget $a, DashboardWidget $b): int => $a->sortOrder() <=> $b->sortOrder(),
        );

        return $widgets;
    }

    public function get(string $id): ?DashboardWidget
    {
        return $this->widgets[$id] ?? null;
    }
}
