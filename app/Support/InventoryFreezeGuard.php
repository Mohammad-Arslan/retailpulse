<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CountScopeType;
use App\Enums\CountSessionStatus;
use App\Models\BinLocation;
use App\Models\CountSession;
use Illuminate\Validation\ValidationException;

final class InventoryFreezeGuard
{
    public static function isFrozen(int $warehouseId, ?int $binLocationId = null, ?int $zoneId = null): bool
    {
        if ($zoneId === null && $binLocationId !== null) {
            $zoneId = BinLocation::query()
                ->whereKey($binLocationId)
                ->value('warehouse_zone_id');
        }

        $query = CountSession::query()
            ->where('warehouse_id', $warehouseId)
            ->where('freeze_mode', true)
            ->whereIn('status', [
                CountSessionStatus::InProgress,
                CountSessionStatus::UnderReview,
                CountSessionStatus::Approved,
            ]);

        if ($binLocationId !== null || $zoneId !== null) {
            $query->where(function ($q) use ($zoneId) {
                $q->where('scope_type', CountScopeType::Full);

                if ($zoneId !== null) {
                    $q->orWhere(function ($inner) use ($zoneId) {
                        $inner->where('scope_type', CountScopeType::Zone)
                            ->where('scope_id', $zoneId);
                    });
                }
            });
        }

        return $query->exists();
    }

    public static function assertNotFrozen(int $warehouseId, ?int $binLocationId = null, ?int $zoneId = null): void
    {
        if (self::isFrozen($warehouseId, $binLocationId, $zoneId)) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Inventory movements are frozen during an active cycle count.'),
            ]);
        }
    }
}
