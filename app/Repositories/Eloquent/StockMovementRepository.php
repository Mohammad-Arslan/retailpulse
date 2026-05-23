<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;

final class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function create(array $attributes): StockMovement
    {
        return StockMovement::query()->create($attributes);
    }

    public function hasOpeningBalance(int $warehouseId, int $variantId, ?int $batchId): bool
    {
        return StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->when(
                $batchId === null,
                fn ($q) => $q->whereNull('batch_id'),
                fn ($q) => $q->where('batch_id', $batchId),
            )
            ->where('reason', StockMovementReason::OpeningBalance)
            ->exists();
    }
}
