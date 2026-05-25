<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PosCart;
use Illuminate\Support\Collection;

interface PosCartRepositoryInterface
{
    public function findById(string $id): ?PosCart;

    public function findByIdWithItems(string $id): ?PosCart;

    /** @return Collection<int, PosCart> */
    public function openCartsForCashier(int $cashierId): Collection;

    public function countOpenCartsForCashier(int $cashierId): int;

    public function create(array $attributes): PosCart;

    public function update(PosCart $cart, array $attributes): PosCart;

    public function nextAvailableSlot(int $cashierId): ?int;
}
