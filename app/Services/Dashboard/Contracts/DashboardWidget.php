<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Contracts;

use App\Models\User;

interface DashboardWidget
{
    public function id(): string;

    public function module(): string;

    /** i18n key under pages.dashboard.widgets.* */
    public function titleKey(): string;

    /**
     * User must have at least one of these permissions to see the widget.
     *
     * @return list<string>
     */
    public function permissions(): array;

    public function sortOrder(): int;

    public function isVisible(User $user): bool;

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array<string, mixed>|null
     */
    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array;
}
