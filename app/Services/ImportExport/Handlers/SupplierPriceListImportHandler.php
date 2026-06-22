<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\Procurement\SupplierPriceListService;

final class SupplierPriceListImportHandler implements ImportHandler
{
    public function __construct(
        private readonly SupplierPriceListService $priceLists,
    ) {}

    public function columns(): array
    {
        return [
            [
                'key' => 'supplier_code',
                'label' => 'Supplier Code',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'list_name',
                'label' => 'Price List Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'valid_from',
                'label' => 'Valid From',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'valid_to',
                'label' => 'Valid To',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'currency_code',
                'label' => 'Currency',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'size' => 3]],
                'default_transforms' => ['trim', 'uppercase', 'nullify_empty'],
            ],
            [
                'key' => 'is_active',
                'label' => 'Active',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']],
                'default_transforms' => ['cast_bool'],
            ],
            [
                'key' => 'variant_sku',
                'label' => 'Variant SKU',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim', 'uppercase'],
            ],
            [
                'key' => 'unit_price',
                'label' => 'Unit Price',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'numeric', 'min' => 0]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'min_qty',
                'label' => 'Min Qty',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric', 'min' => 0]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'lead_time_days',
                'label' => 'Lead Time (Days)',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'integer', 'min' => 0]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        try {
            $item = $this->priceLists->importLine($row, $context->userId);

            return ImportRowResult::success($item->id);
        } catch (\Throwable $e) {
            return ImportRowResult::failure($e->getMessage());
        }
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function afterImport(ImportContext $context): void {}
}
