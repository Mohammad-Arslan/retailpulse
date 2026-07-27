<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Exceptions\ImportExport\ImportRowException;
use App\Models\Inventory;
use App\Services\Accounting\CostService;
use App\Services\ImportExport\Concerns\DeclaresImportSchema;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\Support\InventoryImportSupport;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryImportHandler implements ImportHandler
{
    use DeclaresImportSchema;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryImportSupport $support,
        private readonly CostService $costService,
    ) {}

    public function targetModels(): array
    {
        return [Inventory::class];
    }

    public function columnMap(): array
    {
        return [
            'qty' => 'quantity_on_hand',
        ];
    }

    public function columns(): array
    {
        return InventoryImportSupport::openingStockColumns();
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        try {
            $warehouse = $this->support->resolveWarehouse($row, $context);
            $variant = $this->support->resolveVariant($row, $context);
            $binId = $this->support->resolveBinId($warehouse->id, $row);
        } catch (ImportRowException $e) {
            return ['_row' => [$e->getMessage()]];
        }

        $batchNo = trim((string) ($row['batch_no'] ?? ''));
        $binCode = trim((string) ($row['bin_code'] ?? ''));

        $dedupeKey = implode('|', ['inventory-opening-balance', $warehouse->id, $variant->id, $batchNo, $binCode]);

        if ($context->duplicateTracker->isDuplicate($dedupeKey)) {
            return ['sku' => ['This warehouse, variant, batch, and bin combination is duplicated elsewhere in this file.']];
        }

        $batchId = $this->support->findExistingBatchId($variant, $row);

        if ($this->inventory->hasExistingOpeningBalance($warehouse->id, $variant->id, $batchId, $binId)) {
            return ['sku' => [
                $binId !== null
                    ? 'Opening balance already exists for this warehouse, variant, batch, and bin.'
                    : 'Opening balance already exists for this warehouse, variant, and batch.',
            ]];
        }

        return [];
    }

    public function compositeConstraintAdvisories(): array
    {
        return [
            'This import enforces uniqueness on Warehouse + Variant + Batch (and Bin, when provided) — duplicate rows are rejected on import.',
        ];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        $warehouse = $this->support->resolveWarehouse($row, $context);
        $variant = $this->support->resolveVariant($row, $context);
        $qty = (int) ($row['qty'] ?? 0);
        $unitCost = (float) ($row['unit_cost'] ?? 0);

        if ($qty < 0) {
            throw ImportRowException::fromValidationErrors([
                'qty' => ['Opening stock quantity cannot be negative.'],
            ]);
        }

        if ($unitCost <= 0) {
            throw ImportRowException::fromValidationErrors([
                'unit_cost' => ['Unit cost is required and must be greater than zero.'],
            ]);
        }

        $batchId = $this->support->resolveBatchId($variant, $row);
        $binId = $this->support->resolveBinId($warehouse->id, $row);

        try {
            DB::transaction(function () use (
                $warehouse,
                $variant,
                $qty,
                $unitCost,
                $batchId,
                $binId,
                $context,
            ) {
                $this->inventory->setOpeningBalance(
                    warehouseId: $warehouse->id,
                    variantId: $variant->id,
                    batchId: $batchId,
                    quantity: $qty,
                    userId: $context->userId,
                    notes: 'Opening balance import',
                    binLocationId: $binId,
                );

                $this->costService->createLayerOnReceive(
                    productVariantId: $variant->id,
                    warehouseId: $warehouse->id,
                    qtyReceived: (float) $qty,
                    unitCost: $unitCost,
                    sourceReferenceType: 'opening_stock_import',
                    sourceReferenceId: $variant->id,
                    batchNo: $batchId ? (string) $batchId : null,
                    receivedAt: now(),
                );
            });
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
