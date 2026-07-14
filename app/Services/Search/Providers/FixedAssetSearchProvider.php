<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\FixedAsset;
use App\Models\User;
use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class FixedAssetSearchProvider extends AbstractSearchProvider
{
    public function __construct(
        private readonly AccountingModuleGate $modules,
    ) {}

    public function id(): string
    {
        return 'fixed_assets';
    }

    public function category(): string
    {
        return 'assets';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function priority(): int
    {
        return 75;
    }

    public function permissions(): array
    {
        return ['accounting.manage-assets'];
    }

    public function isAvailable(User $user, BranchContext $context): bool
    {
        if (! parent::isAvailable($user, $context)) {
            return false;
        }

        return in_array('fixed_assets', $this->modules->enabledModules($context->branchId), true);
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = FixedAsset::query()
            ->where(function ($q) use ($like, $query): void {
                $q->where('asset_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('location', 'like', $like);
                if ($this->looksLikeCode($query)) {
                    $q->orWhere('asset_code', 'like', $query.'%');
                }
            });

        $this->scopeBranch($builder, $context);

        return $builder->orderBy('asset_code')->limit($limit)->get()->map(function (FixedAsset $asset): SearchResult {
            return new SearchResult(
                id: 'asset-'.$asset->id,
                provider: $this->id(),
                category: $this->category(),
                title: $asset->name,
                subtitle: trim(($asset->asset_code ?? '').' · '.($asset->location ?? ''), ' ·'),
                routeName: 'admin.accounting.fixed-assets.index',
                icon: $this->icon(),
                score: 75.0,
            );
        })->all();
    }
}
