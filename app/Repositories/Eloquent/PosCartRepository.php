<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PosCartStatus;
use App\Models\PosCart;
use App\Repositories\Contracts\PosCartRepositoryInterface;
use Illuminate\Support\Collection;

final class PosCartRepository implements PosCartRepositoryInterface
{
    public function findById(string $id): ?PosCart
    {
        return PosCart::query()->find($id);
    }

    public function findByIdWithItems(string $id): ?PosCart
    {
        return PosCart::query()
            ->with(['items.variant', 'items.product', 'cashier', 'branch'])
            ->find($id);
    }

    /** @return Collection<int, PosCart> */
    public function openCartsForCashier(int $cashierId): Collection
    {
        return PosCart::query()
            ->with(['items'])
            ->where('cashier_id', $cashierId)
            ->whereIn('status', [
                PosCartStatus::Active->value,
                PosCartStatus::Suspended->value,
                PosCartStatus::Completing->value,
            ])
            ->orderBy('slot')
            ->get();
    }

    public function countOpenCartsForCashier(int $cashierId): int
    {
        return PosCart::query()
            ->where('cashier_id', $cashierId)
            ->whereIn('status', [
                PosCartStatus::Active->value,
                PosCartStatus::Suspended->value,
                PosCartStatus::Completing->value,
            ])
            ->count();
    }

    public function create(array $attributes): PosCart
    {
        return PosCart::query()->create($attributes);
    }

    public function update(PosCart $cart, array $attributes): PosCart
    {
        $cart->update($attributes);

        return $cart->fresh() ?? $cart;
    }

    public function nextAvailableSlot(int $cashierId): ?int
    {
        $usedSlots = PosCart::query()
            ->where('cashier_id', $cashierId)
            ->whereIn('status', [
                PosCartStatus::Active->value,
                PosCartStatus::Suspended->value,
                PosCartStatus::Completing->value,
            ])
            ->pluck('slot')
            ->all();

        for ($slot = 1; $slot <= 5; $slot++) {
            if (! in_array($slot, $usedSlots, true)) {
                return $slot;
            }
        }

        return null;
    }
}
