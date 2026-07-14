<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\User;
use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Services\Hr\Contracts\HrPayrollModuleGate;

final class NavigationComposer
{
    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly AccountingModuleGate $accountingModules,
        private readonly HrPayrollModuleGate $hrModules,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user, ?int $branchId): array
    {
        $enabledModules = array_values(array_unique([
            ...$this->accountingModules->enabledModules($branchId),
            ...$this->hrModules->enabledModules($branchId),
        ]));
        $tree = [];

        foreach ($this->registry->sections() as $section) {
            $visibleItems = [];

            foreach ($section->items as $item) {
                if (! $this->itemVisible($user, $item, $enabledModules)) {
                    continue;
                }

                $visibleItems[] = $item;
            }

            if ($visibleItems === []) {
                continue;
            }

            usort(
                $visibleItems,
                static fn (NavigationItem $a, NavigationItem $b): int => $a->order <=> $b->order,
            );

            $tree[] = (new NavigationSection(
                $section->id,
                $section->labelKey,
                $section->order,
                $visibleItems,
            ))->toArray();
        }

        return $tree;
    }

    /**
     * Unfiltered items for search providers that apply their own permission checks.
     *
     * @return list<NavigationItem>
     */
    public function searchableItems(): array
    {
        return $this->registry->allItems();
    }

    /**
     * @param  list<string>  $enabledModules
     */
    public function itemVisible(User $user, NavigationItem $item, array $enabledModules): bool
    {
        if ($item->module !== null && ! in_array($item->module, $enabledModules, true)) {
            return false;
        }

        if ($item->permissionsAny !== []) {
            foreach ($item->permissionsAny as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }

        if ($item->permission === null || $item->permission === '') {
            return true;
        }

        return $user->can($item->permission);
    }
}
