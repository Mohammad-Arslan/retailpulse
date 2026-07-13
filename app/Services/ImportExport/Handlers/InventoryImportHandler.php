<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Exceptions\ImportExport\ImportRowException;
use App\Services\Accounting\CostService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\Support\InventoryImportSupport;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryImportHandler implements ImportHandler
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryImportSupport $support,
        private readonly CostService $costService,
    ) {}

    public function columns(): array
    {
        return InventoryImportSupport::openingStockColumns();
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
