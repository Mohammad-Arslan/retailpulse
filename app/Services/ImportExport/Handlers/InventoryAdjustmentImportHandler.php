<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\DTOs\Inventory\AdjustStockData;
use App\Enums\StockMovementReason;
use App\Exceptions\ImportExport\ImportRowException;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\Support\InventoryImportSupport;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;

final class InventoryAdjustmentImportHandler implements ImportHandler
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryImportSupport $support,
    ) {}

    public function columns(): array
    {
        return InventoryImportSupport::adjustmentColumns();
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        $warehouse = $this->support->resolveWarehouse($row, $context);
        $variant = $this->support->resolveVariant($row, $context);
        $qtyDelta = (int) ($row['qty_delta'] ?? 0);

        if ($qtyDelta === 0) {
            throw ImportRowException::fromValidationErrors([
                'qty_delta' => ['Quantity delta must not be zero.'],
            ]);
        }

        $reasonValue = strtolower(trim((string) ($row['reason'] ?? '')));
        $reason = match ($reasonValue) {
            'adjustment' => StockMovementReason::Adjustment,
            'damaged' => StockMovementReason::Damaged,
            default => throw ImportRowException::fromValidationErrors([
                'reason' => ['Reason must be adjustment or damaged for bulk import.'],
            ]),
        };

        $batchId = $this->support->resolveBatchId($variant, $row);
        $notes = trim((string) ($row['notes'] ?? ''));

        try {
            $this->inventory->adjust(new AdjustStockData(
                warehouseId: $warehouse->id,
                variantId: $variant->id,
                batchId: $batchId,
                quantity: $qtyDelta,
                reason: $reason,
                userId: $context->userId,
                notes: $notes !== '' ? $notes : null,
            ));
        } catch (ValidationException $e) {
            throw ImportRowException::fromValidationErrors($e->errors());
        }

        return ImportRowResult::success($variant->id);
    }

    public function afterImport(ImportContext $context): void
    {
        //
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
