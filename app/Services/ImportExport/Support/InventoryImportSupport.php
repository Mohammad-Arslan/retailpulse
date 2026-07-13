<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Support;

use App\Exceptions\ImportExport\ImportRowException;
use App\Models\BinLocation;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\ImportExport\ImportContext;
use App\Support\TenantImportScope;

final class InventoryImportSupport
{
    public function resolveWarehouse(array $row, ImportContext $context): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('code', $row['warehouse_code'])
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->first();

        if ($warehouse === null) {
            throw ImportRowException::fromValidationErrors([
                'warehouse_code' => ['Warehouse not found for this tenant.'],
            ]);
        }

        return $warehouse;
    }

    public function resolveVariant(array $row, ImportContext $context): ProductVariant
    {
        $variant = ProductVariant::query()
            ->with('product')
            ->where('sku', $row['sku'])
            ->whereHas('product', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->first();

        if ($variant === null) {
            throw ImportRowException::fromValidationErrors([
                'sku' => ['Product variant not found for this tenant.'],
            ]);
        }

        return $variant;
    }

    public function resolveBatchId(ProductVariant $variant, array $row): ?int
    {
        $batchNo = trim((string) ($row['batch_no'] ?? ''));
        $expiryDate = trim((string) ($row['expiry_date'] ?? ''));
        $tracksBatches = (bool) ($variant->product?->track_batches ?? false);

        if (! $tracksBatches) {
            if ($batchNo !== '' || $expiryDate !== '') {
                throw ImportRowException::fromValidationErrors([
                    'batch_no' => ['This product does not track batches.'],
                ]);
            }

            return null;
        }

        if ($batchNo === '') {
            throw ImportRowException::fromValidationErrors([
                'batch_no' => ['Batch number is required for batch-tracked products.'],
            ]);
        }

        $batch = ProductBatch::query()->firstOrCreate(
            [
                'product_variant_id' => $variant->id,
                'batch_no' => $batchNo,
            ],
            [
                'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
            ],
        );

        if ($expiryDate !== '' && $batch->expiry_date?->format('Y-m-d') !== $expiryDate) {
            $batch->update(['expiry_date' => $expiryDate]);
        }

        return $batch->id;
    }

    public function resolveBinId(int $warehouseId, array $row): ?int
    {
        $binCode = trim((string) ($row['bin_code'] ?? ''));

        if ($binCode === '') {
            return null;
        }

        $bin = BinLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('bin_code', $binCode)
            ->where('is_active', true)
            ->first();

        if ($bin === null) {
            throw ImportRowException::fromValidationErrors([
                'bin_code' => ['Bin location not found in this warehouse.'],
            ]);
        }

        return $bin->id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function openingStockColumns(): array
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
                'key' => 'unit_cost',
                'label' => 'Unit Cost',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'numeric'], ['rule' => 'min:0']],
                'default_transforms' => ['cast_float'],
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
            [
                'key' => 'bin_code',
                'label' => 'Bin Code',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string']],
                'default_transforms' => ['trim', 'uppercase'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adjustmentColumns(): array
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
                'key' => 'batch_no',
                'label' => 'Batch Number',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string']],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'reason',
                'label' => 'Reason',
                'required' => true,
                'default_rules' => [
                    ['rule' => 'required'],
                    ['rule' => 'in_list', 'values' => ['adjustment', 'damaged']],
                ],
                'default_transforms' => ['trim', 'lowercase'],
            ],
            [
                'key' => 'qty_delta',
                'label' => 'Quantity Delta',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'numeric']],
                'default_transforms' => ['cast_int'],
            ],
            [
                'key' => 'notes',
                'label' => 'Notes',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string']],
                'default_transforms' => ['trim'],
            ],
        ];
    }
}
