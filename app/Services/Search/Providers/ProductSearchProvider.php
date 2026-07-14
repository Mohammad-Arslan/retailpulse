<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\Product;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class ProductSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'products';
    }

    public function category(): string
    {
        return 'products';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function priority(): int
    {
        return 20;
    }

    public function permissions(): array
    {
        return ['products.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $products = Product::query()
            ->with(['variants' => fn ($q) => $q->limit(3)])
            ->where('is_active', true)
            ->where(function ($q) use ($like, $query): void {
                $q->where('name', 'like', $like)
                    ->orWhereHas('variants', function ($vq) use ($like, $query): void {
                        $vq->where('sku', 'like', $like)
                            ->orWhere('barcode', 'like', $like)
                            ->orWhere('name', 'like', $like);
                        if ($this->looksLikeCode($query)) {
                            $vq->orWhere('sku', 'like', $query.'%')
                                ->orWhere('barcode', '=', $query);
                        }
                    });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $products->map(function (Product $product): SearchResult {
            $variant = $product->variants->first();
            $sku = $variant?->sku;

            return new SearchResult(
                id: 'product-'.$product->id,
                provider: $this->id(),
                category: $this->category(),
                title: $product->name,
                subtitle: $sku ? 'SKU: '.$sku : null,
                meta: array_filter(['sku' => $sku]),
                routeName: 'admin.products.show',
                routeParams: ['product' => $product->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
