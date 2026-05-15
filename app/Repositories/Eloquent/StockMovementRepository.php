<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;

final class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function create(array $attributes): StockMovement
    {
        return StockMovement::query()->create($attributes);
    }
}
