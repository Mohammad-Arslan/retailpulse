<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class PurchaseOrderSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'purchase_orders';
    }

    public function category(): string
    {
        return 'purchasing';
    }

    public function icon(): string
    {
        return 'clipboard-list';
    }

    public function priority(): int
    {
        return 45;
    }

    public function permissions(): array
    {
        return ['procurement.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->where(function ($q) use ($like): void {
                $q->where('reference_no', 'like', $like)
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $like));
            });

        $this->scopeBranch($builder, $context);

        return $builder->latest('id')->limit($limit)->get()->map(function (PurchaseOrder $po): SearchResult {
            return new SearchResult(
                id: 'po-'.$po->id,
                provider: $this->id(),
                category: $this->category(),
                title: $po->reference_no ?? 'PO #'.$po->id,
                subtitle: trim(($po->status?->value ?? '').' · '.($po->supplier?->name ?? ''), ' ·'),
                meta: ['status' => $po->status?->value],
                routeName: 'admin.purchase-orders.show',
                routeParams: ['purchase_order' => $po->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
