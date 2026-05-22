<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\DTOs\Inventory\AdjustStockData;
use App\Enums\StockMovementReason;
use App\Exceptions\ImportExport\ImportRowException;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\TenantImportScope;
use App\Services\InventoryService;

final class InventoryImportHandler implements ImportHandler
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function columns(): array
    {
        return [
            [
                'key' => 'warehouse_code',
                'label' => 'Warehouse Code',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string']],
                'default_transforms' => ['trim', 'uppercase'],
            ],
            [
                'key' => 'sku',
                'label' => 'SKU',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string']],
                'default_transforms' => ['trim', 'uppercase'],
            ],
            [
                'key' => 'qty',
                'label' => 'Quantity',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'numeric']],
                'default_transforms' => ['cast_int'],
            ],
            [
                'key' => 'batch_no',
                'label' => 'Batch Number',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string']],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'expiry_date',
                'label' => 'Expiry Date',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['date_normalize'],
            ],
        ];
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

        $warehouse = Warehouse::query()
            ->where('code', $row['warehouse_code'])
            ->whereHas('branch', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->first();

        if ($warehouse === null) {
            throw ImportRowException::fromValidationErrors([
                'warehouse_code' => ['Warehouse not found for this tenant.'],
            ]);
        }

        $variant = ProductVariant::query()
            ->where('sku', $row['sku'])
            ->whereHas('product', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->first();

        if ($variant === null) {
            throw ImportRowException::fromValidationErrors([
                'sku' => ['Product variant not found for this tenant.'],
            ]);
        }

        $qty = (int) ($row['qty'] ?? 0);

        if ($qty === 0) {
            throw ImportRowException::fromValidationErrors([
                'qty' => ['Quantity must not be zero.'],
            ]);
        }

        $this->inventory->adjust(new AdjustStockData(
            warehouseId: $warehouse->id,
            variantId: $variant->id,
            batchId: null,
            quantity: $qty,
            reason: StockMovementReason::Adjustment,
            userId: $context->userId,
            notes: 'Opening balance import',
        ));

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
