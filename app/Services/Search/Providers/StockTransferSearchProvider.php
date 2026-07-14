<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class StockTransferSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'transfers';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function priority(): int
    {
        return 50;
    }

    public function permissions(): array
    {
        return ['inventory.transfer', 'inventory.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = StockTransfer::query()
            ->with(['fromWarehouse:id,name,branch_id', 'toWarehouse:id,name,branch_id'])
            ->where('reference_no', 'like', $like);

        if ($context->branchId !== null) {
            $branchId = $context->branchId;
            $builder->where(function ($q) use ($branchId): void {
                $q->whereHas('fromWarehouse', fn ($w) => $w->where('branch_id', $branchId))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->where('branch_id', $branchId));
            });
        } elseif ($context->accessibleBranchIds !== null) {
            $ids = $context->accessibleBranchIds;
            $builder->where(function ($q) use ($ids): void {
                $q->whereHas('fromWarehouse', fn ($w) => $w->whereIn('branch_id', $ids))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->whereIn('branch_id', $ids));
            });
        }

        return $builder->latest('id')->limit($limit)->get()->map(function (StockTransfer $transfer): SearchResult {
            $from = $transfer->fromWarehouse?->name;
            $to = $transfer->toWarehouse?->name;

            return new SearchResult(
                id: 'transfer-'.$transfer->id,
                provider: $this->id(),
                category: $this->category(),
                title: $transfer->reference_no ?? 'Transfer #'.$transfer->id,
                subtitle: trim(($from ?? '?').' → '.($to ?? '?')),
                routeName: 'admin.stock-transfers.show',
                routeParams: ['stock_transfer' => $transfer->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
