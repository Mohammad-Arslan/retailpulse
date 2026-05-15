<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\StockMovement;

interface StockMovementRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): StockMovement;
}
