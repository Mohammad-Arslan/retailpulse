<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StockTransferRepositoryInterface
{
    public function findByIdWithRelations(int $id): ?StockTransfer;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): StockTransfer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StockTransfer $transfer, array $attributes): StockTransfer;

    public function nextReferenceNo(): string;
}
