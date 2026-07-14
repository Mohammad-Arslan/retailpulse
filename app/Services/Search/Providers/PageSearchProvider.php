<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Services\Navigation\NavigationComposer;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Services\Search\Support\NavLabelResolver;
use App\Support\BranchContext;

final class PageSearchProvider extends AbstractSearchProvider
{
    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly NavigationComposer $composer,
        private readonly AccountingModuleGate $modules,
        private readonly NavLabelResolver $labels,
    ) {}

    public function id(): string
    {
        return 'pages';
    }

    public function category(): string
    {
        return 'pages';
    }

    public function icon(): string
    {
        return 'layout-dashboard';
    }

    public function priority(): int
    {
        return 10;
    }

    public function permissions(): array
    {
        return [];
    }

    public function isAvailable(User $user, BranchContext $context): bool
    {
        return true;
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $q = mb_strtolower($query);
        $enabled = $this->modules->enabledModules($context->branchId);
        $results = [];

        foreach ($this->registry->allItems() as $item) {
            if (! $this->composer->itemVisible($user, $item, $enabled)) {
                continue;
            }

            $title = $this->labels->label($item->titleKey);
            $haystack = mb_strtolower(implode(' ', [
                $title,
                $item->titleKey,
                ...$item->keywords,
            ]));

            if (! str_contains($haystack, $q)) {
                continue;
            }

            $score = str_starts_with(mb_strtolower($title), $q) ? 100.0 : 50.0;

            $sectionLabelKey = match ($item->group) {
                'inventory' => 'inventorySection',
                'customers' => 'customersSection',
                'accounting' => 'accountingSection',
                default => $item->group,
            };

            $results[] = new SearchResult(
                id: 'page-'.$item->id,
                provider: $this->id(),
                category: $this->category(),
                title: $title,
                subtitle: $this->labels->label($sectionLabelKey),
                meta: [
                    'titleKey' => $item->titleKey,
                    'sectionKey' => $sectionLabelKey,
                ],
                routeName: $item->route,
                icon: $item->icon,
                score: $score,
            );

            if (count($results) >= $limit * 3) {
                break;
            }
        }

        return $results;
    }
}
