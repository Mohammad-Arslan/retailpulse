<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DemoProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->where('code', 'HQ')->first();
        $supplier = Supplier::query()->where('code', 'SUP-ACME')->first();
        $creator = User::query()->where('email', 'manager@retailpulse.local')->first()
          ?? User::query()->first();

        if ($branch === null || $supplier === null || $creator === null) {
            return;
        }

        $variant = ProductVariant::query()->where('sku', 'DEMO-BEV-001')->first();

        if ($variant === null) {
            return;
        }

        $po = PurchaseOrder::query()->firstOrCreate(
            ['reference_no' => 'PO-DEMO-0001'],
            [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Draft,
                'currency_code' => $branch->currency,
                'exchange_rate' => 1,
                'subtotal' => 102.00,
                'tax_total' => 0,
                'total' => 102.00,
                'functional_total' => 102.00,
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ],
        );

        PurchaseOrderItem::query()->firstOrCreate(
            [
                'purchase_order_id' => $po->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'qty_ordered' => 120,
                'unit_price' => 0.85,
                'tax_rate' => 0,
                'line_total' => 102.00,
            ],
        );
    }
}
