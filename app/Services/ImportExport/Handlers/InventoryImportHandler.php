<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Exceptions\ImportExport\ImportRowException;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\Support\InventoryImportSupport;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;

final class InventoryImportHandler implements ImportHandler
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryImportSupport $support,
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

        if ($qty < 0) {
            throw ImportRowException::fromValidationErrors([
                'qty' => ['Opening stock quantity cannot be negative.'],
            ]);
        }

        $batchId = $this->support->resolveBatchId($variant, $row);
        $binId = $this->support->resolveBinId($warehouse->id, $row);

        try {
            $this->inventory->setOpeningBalance(
                warehouseId: $warehouse->id,
                variantId: $variant->id,
                batchId: $batchId,
                quantity: $qty,
                userId: $context->userId,
                notes: 'Opening balance import',
                binLocationId: $binId,
            );
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
