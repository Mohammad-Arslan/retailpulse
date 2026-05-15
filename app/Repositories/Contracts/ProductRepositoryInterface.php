<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Product;

    public function findByIdWithRelations(int $id): ?Product;

    public function create(array $attributes): Product;

    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;

    /**
     * @return Collection<int, array{id: int, sku: string, name: string, product_name: string, sell_price: string}>
     */
    public function searchVariants(string $term, ?int $excludeProductId = null, int $limit = 20): Collection;
}
