<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierPriceList;
use App\Models\SupplierPriceListItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierPriceListService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function create(Supplier $supplier, array $data, array $items, int $userId): SupplierPriceList
    {
        return DB::transaction(function () use ($supplier, $data, $items, $userId) {
            $list = $supplier->priceLists()->create([
                'name' => $data['name'],
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'currency_code' => $data['currency_code'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncItems($list, $items);

            return $list->fresh(['items.variant.product']) ?? $list;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function update(SupplierPriceList $list, array $data, array $items, int $userId): SupplierPriceList
    {
        return DB::transaction(function () use ($list, $data, $items, $userId) {
            $list->update([
                'name' => $data['name'],
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'currency_code' => $data['currency_code'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_by' => $userId,
            ]);

            $list->items()->delete();
            $this->syncItems($list, $items);

            return $list->fresh(['items.variant.product']) ?? $list;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function importLine(array $row, int $userId): SupplierPriceListItem
    {
        $supplier = Supplier::query()->where('code', $row['supplier_code'])->first();
        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_code' => __('Supplier :code was not found.', ['code' => $row['supplier_code']]),
            ]);
        }

        $variant = ProductVariant::query()->where('sku', $row['variant_sku'])->first();
        if ($variant === null) {
            throw ValidationException::withMessages([
                'variant_sku' => __('Variant SKU :sku was not found.', ['sku' => $row['variant_sku']]),
            ]);
        }

        $listName = (string) $row['list_name'];
        $currencyCode = strtoupper((string) ($row['currency_code'] ?? $supplier->currency_code ?? 'USD'));

        $list = SupplierPriceList::query()
            ->where('supplier_id', $supplier->id)
            ->where('name', $listName)
            ->first();

        if ($list === null) {
            $list = $this->create($supplier, [
                'name' => $listName,
                'valid_from' => $row['valid_from'] ?? null,
                'valid_to' => $row['valid_to'] ?? null,
                'currency_code' => $currencyCode,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ], [], $userId);
        } elseif (
            ! empty($row['currency_code'])
            && strtoupper((string) $row['currency_code']) !== strtoupper((string) $list->currency_code)
        ) {
            throw ValidationException::withMessages([
                'currency_code' => __('Price list :name already uses currency :currency.', [
                    'name' => $listName,
                    'currency' => $list->currency_code,
                ]),
            ]);
        }

        return $list->items()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'unit_price' => $row['unit_price'],
                'min_qty' => $row['min_qty'] ?? 1,
                'lead_time_days' => $row['lead_time_days'] ?? null,
            ],
        );
    }

    public function delete(SupplierPriceList $list): void
    {
        if ($list->items()->exists()) {
            throw ValidationException::withMessages([
                'price_list' => __('Deactivate the price list instead of deleting one with items.'),
            ]);
        }

        $list->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(SupplierPriceList $list, array $items): void
    {
        foreach ($items as $item) {
            $list->items()->create([
                'product_variant_id' => $item['product_variant_id'],
                'unit_price' => $item['unit_price'],
                'min_qty' => $item['min_qty'] ?? 1,
                'lead_time_days' => $item['lead_time_days'] ?? null,
            ]);
        }
    }
}
