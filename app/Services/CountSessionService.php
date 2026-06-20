<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CountSession\CreateCountSessionData;
use App\DTOs\CountSession\SubmitCountLinesData;
use App\Enums\CountScopeType;
use App\Enums\CountSessionStatus;
use App\Enums\StockMovementReason;
use App\Models\CountSession;
use App\Models\CountSessionLine;
use App\Models\Inventory;
use App\Repositories\Contracts\CountSessionRepositoryInterface;
use App\Support\InventoryFreezeGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountSessionService
{
    public function __construct(
        private readonly CountSessionRepositoryInterface $sessions,
        private readonly InventoryService $inventory,
    ) {}

    public function create(CreateCountSessionData $data): CountSession
    {
        if ($data->scopeType !== CountScopeType::Full && $data->scopeId === null) {
            throw ValidationException::withMessages([
                'scope_id' => __('Scope ID is required for zone or category counts.'),
            ]);
        }

        return CountSession::query()->create([
            'reference_no' => $this->sessions->nextReferenceNo(),
            'branch_id' => $data->branchId,
            'warehouse_id' => $data->warehouseId,
            'scope_type' => $data->scopeType,
            'scope_id' => $data->scopeId,
            'status' => CountSessionStatus::Draft,
            'blind_count' => $data->blindCount,
            'freeze_mode' => $data->freezeMode,
            'variance_threshold_pct' => $data->varianceThresholdPct,
            'variance_threshold_value' => $data->varianceThresholdValue,
            'created_by' => $data->userId,
        ]);
    }

    public function start(CountSession $session): CountSession
    {
        if ($session->status !== CountSessionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => __('Only draft sessions can be started.'),
            ]);
        }

        return DB::transaction(function () use ($session) {
            $this->generateLines($session);

            $session->update(['status' => CountSessionStatus::InProgress]);

            return $this->sessions->findByIdWithRelations($session->id) ?? $session;
        });
    }

    public function submitCounts(CountSession $session, SubmitCountLinesData $data): CountSession
    {
        if ($session->status !== CountSessionStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => __('Counts can only be submitted while session is in progress.'),
            ]);
        }

        return DB::transaction(function () use ($session, $data) {
            foreach ($data->lines as $lineInput) {
                $line = CountSessionLine::query()
                    ->where('count_session_id', $session->id)
                    ->where('id', $lineInput['line_id'])
                    ->first();

                if ($line === null) {
                    continue;
                }

                $countedQty = (int) $lineInput['counted_qty'];
                $variance = $countedQty - $line->system_qty;
                $sellPrice = (float) ($line->variant?->sell_price ?? 0);

                $line->update([
                    'counted_qty' => $countedQty,
                    'variance_qty' => $variance,
                    'variance_value' => abs($variance) * $sellPrice,
                ]);
            }

            $session->update(['status' => CountSessionStatus::UnderReview]);

            return $this->sessions->findByIdWithRelations($session->id) ?? $session;
        });
    }

    public function approve(CountSession $session, int $userId): CountSession
    {
        if ($session->status !== CountSessionStatus::UnderReview) {
            throw ValidationException::withMessages([
                'status' => __('Only sessions under review can be approved.'),
            ]);
        }

        $session->load('lines');

        $this->assertVarianceWithinThreshold($session);

        $session->update([
            'status' => CountSessionStatus::Approved,
            'approved_by' => $userId,
        ]);

        return $this->sessions->findByIdWithRelations($session->id) ?? $session;
    }

    public function post(CountSession $session, int $userId): CountSession
    {
        if (! $session->status->canPost()) {
            throw ValidationException::withMessages([
                'status' => __('Only approved sessions can be posted.'),
            ]);
        }

        $session->load('lines');

        return DB::transaction(function () use ($session, $userId) {
            foreach ($session->lines as $line) {
                if ($line->variance_qty === null || $line->variance_qty === 0) {
                    continue;
                }

                $this->inventory->applyDelta(
                    warehouseId: $session->warehouse_id,
                    variantId: $line->product_variant_id,
                    batchId: $this->resolveBatchId($line),
                    qtyDelta: $line->variance_qty,
                    reason: StockMovementReason::CycleCountAdjustment,
                    userId: $userId,
                    referenceType: CountSession::class,
                    referenceId: $session->id,
                    notes: $line->adjustment_reason ?? "Cycle count {$session->reference_no}",
                    binLocationId: $line->bin_location_id,
                );
            }

            $session->update([
                'status' => CountSessionStatus::Posted,
                'posted_at' => now(),
            ]);

            return $this->sessions->findByIdWithRelations($session->id) ?? $session;
        });
    }

    public function isFrozen(int $warehouseId, ?int $binLocationId = null, ?int $zoneId = null): bool
    {
        return InventoryFreezeGuard::isFrozen($warehouseId, $binLocationId, $zoneId);
    }

    private function generateLines(CountSession $session): void
    {
        $query = Inventory::query()
            ->with(['variant', 'batch', 'binLocation'])
            ->where('warehouse_id', $session->warehouse_id)
            ->where(function ($q) {
                $q->where('quantity_on_hand', '>', 0)
                    ->orWhere('quantity_in_quarantine', '>', 0);
            });

        if ($session->scope_type === CountScopeType::Zone && $session->scope_id !== null) {
            $query->whereHas('binLocation', fn ($b) => $b->where('warehouse_zone_id', $session->scope_id));
        }

        if ($session->scope_type === CountScopeType::Category && $session->scope_id !== null) {
            $query->whereHas('variant.product', fn ($p) => $p->where('category_id', $session->scope_id));
        }

        foreach ($query->get() as $inventory) {
            CountSessionLine::query()->create([
                'count_session_id' => $session->id,
                'product_variant_id' => $inventory->product_variant_id,
                'bin_location_id' => $inventory->bin_location_id,
                'batch_no' => $inventory->batch?->batch_no,
                'system_qty' => $inventory->quantity_on_hand,
            ]);
        }
    }

    private function assertVarianceWithinThreshold(CountSession $session): void
    {
        $totalVarianceValue = $session->lines->sum(fn ($line) => abs((float) ($line->variance_value ?? 0)));

        if (
            $session->variance_threshold_value !== null
            && $totalVarianceValue > (float) $session->variance_threshold_value
        ) {
            throw ValidationException::withMessages([
                'variance' => __('Total variance value exceeds the approval threshold.'),
            ]);
        }

        foreach ($session->lines as $line) {
            if ($line->system_qty <= 0 || $line->variance_qty === null) {
                continue;
            }

            $pct = abs($line->variance_qty) / $line->system_qty * 100;

            if (
                $session->variance_threshold_pct !== null
                && $pct > (float) $session->variance_threshold_pct
            ) {
                throw ValidationException::withMessages([
                    'variance' => __('Line variance percentage exceeds the approval threshold.'),
                ]);
            }
        }
    }

    private function resolveBatchId(CountSessionLine $line): ?int
    {
        if ($line->batch_no === null) {
            return null;
        }

        $batch = $line->variant?->batches()
            ->where('batch_no', $line->batch_no)
            ->first();

        return $batch?->id;
    }
}
