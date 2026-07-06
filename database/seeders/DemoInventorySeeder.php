<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

final class DemoInventorySeeder extends Seeder
{
    /**
     * @var array<string, int>
     */
    private const STOCK_BY_SKU = [
        'DEMO-BEV-001' => 120,
        'DEMO-SNK-001' => 80,
        'DEMO-ELC-001' => 45,
        'DEMO-DAI-001' => 60,
    ];

    public function run(): void
    {
        $inventory = app(InventoryService::class);
        $adminId = User::query()->value('id');

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->get();

        if ($warehouses->isEmpty()) {
            $this->command?->warn('DemoInventorySeeder: no active warehouses found.');

            return;
        }

        foreach (self::STOCK_BY_SKU as $sku => $quantity) {
            $variant = ProductVariant::query()->where('sku', $sku)->first();

            if ($variant === null) {
                continue;
            }

            $variant->load('product');
            $product = $variant->product;

            if ($product === null || ! $product->tracksInventory()) {
                continue;
            }

            $batchId = null;

            if ($product->track_batches) {
                $batch = ProductBatch::query()->firstOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'batch_no' => 'LOT-DEMO-001',
                    ],
                    [
                        'expiry_date' => now()->addMonths(3)->toDateString(),
                    ],
                );
                $batchId = $batch->id;
            }

            foreach ($warehouses as $warehouse) {
                $warehouseQty = (int) max(1, round($quantity * ($warehouse->is_default ? 0.7 : 0.3)));

                try {
                    $inventory->setOpeningBalance(
                        warehouseId: $warehouse->id,
                        variantId: $variant->id,
                        batchId: $batchId,
                        quantity: $warehouseQty,
                        userId: $adminId !== null ? (int) $adminId : null,
                        notes: 'Demo opening balance',
                    );
                } catch (ValidationException) {
                    // Opening balance already exists for this warehouse/variant/batch — skip.
                }
            }
        }
    }
}
