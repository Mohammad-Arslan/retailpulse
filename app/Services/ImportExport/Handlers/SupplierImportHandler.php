<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\Procurement\SupplierService;

final class SupplierImportHandler implements ImportHandler
{
    public function __construct(
        private readonly SupplierService $suppliers,
    ) {}

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'email', 'label' => 'Email', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'email']], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'phone', 'label' => 'Phone', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'tax_registration_no', 'label' => 'Tax Registration No', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'currency_code', 'label' => 'Currency', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'size' => 3]], 'default_transforms' => ['trim']],
            ['key' => 'is_active', 'label' => 'Active', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']], 'default_transforms' => ['cast_bool']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        try {
            $supplier = $this->suppliers->upsertByCode($row);

            return ImportRowResult::success($supplier->id);
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
