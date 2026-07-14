<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\GoodsReceivingNote;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class GoodsReceiptSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'goods_receipts';
    }

    public function category(): string
    {
        return 'purchasing';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function priority(): int
    {
        return 46;
    }

    public function permissions(): array
    {
        return ['procurement.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = GoodsReceivingNote::query()
            ->with('supplier:id,name')
            ->where(function ($q) use ($like): void {
                $q->where('reference_no', 'like', $like)
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $like));
            });

        $this->scopeBranch($builder, $context);

        return $builder->latest('id')->limit($limit)->get()->map(function (GoodsReceivingNote $grn): SearchResult {
            return new SearchResult(
                id: 'grn-'.$grn->id,
                provider: $this->id(),
                category: $this->category(),
                title: $grn->reference_no ?? 'GRN #'.$grn->id,
                subtitle: $grn->supplier?->name,
                routeName: 'admin.goods-receiving-notes.show',
                routeParams: ['goods_receiving_note' => $grn->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
