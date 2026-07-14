<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\User;
use App\Support\BranchContext;

final class DashboardComposer
{
    public function __construct(
        private readonly DashboardWidgetRegistry $registry,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     module: string,
     *     title_key: string,
     *     sort_order: int,
     *     data: array<string, mixed>
     * }>
     */
    public function compose(User $user, BranchContext $context): array
    {
        $branchId = $context->branchId;
        $accessibleBranchIds = $context->accessibleBranchIds;
        $payload = [];

        foreach ($this->registry->all() as $widget) {
            if (! $widget->isVisible($user)) {
                continue;
            }

            $data = $widget->data($user, $branchId, $accessibleBranchIds);

            if ($data === null) {
                continue;
            }

            $payload[] = [
                'id' => $widget->id(),
                'module' => $widget->module(),
                'title_key' => $widget->titleKey(),
                'sort_order' => $widget->sortOrder(),
                'data' => $data,
            ];
        }

        return $payload;
    }
}
