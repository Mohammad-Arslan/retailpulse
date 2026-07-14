<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\Dashboard\BusinessExceptionFeedService;

final class BusinessExceptionsWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly BusinessExceptionFeedService $exceptions,
    ) {}

    public function id(): string
    {
        return 'business_exceptions';
    }

    public function module(): string
    {
        return 'operations';
    }

    public function titleKey(): string
    {
        return 'exceptions';
    }

    public function permissions(): array
    {
        return ['dashboard.exceptions.view'];
    }

    public function sortOrder(): int
    {
        return 5;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        return [
            'items' => $this->exceptions->forUser($user, $branchId, $accessibleBranchIds),
        ];
    }
}
