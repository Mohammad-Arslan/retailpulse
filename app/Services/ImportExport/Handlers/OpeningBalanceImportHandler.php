<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Services\Accounting\OpeningBalanceImportService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;

final class OpeningBalanceImportHandler implements ImportHandler
{
    public function __construct(
        private readonly OpeningBalanceImportService $openingBalances,
    ) {}

    public function columns(): array
    {
        return [
            ['key' => 'account_code', 'label' => 'Account Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'debit', 'label' => 'Debit', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric', 'min' => 0]], 'default_transforms' => ['cast_decimal']],
            ['key' => 'credit', 'label' => 'Credit', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric', 'min' => 0]], 'default_transforms' => ['cast_decimal']],
            ['key' => 'description', 'label' => 'Description', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'party_type', 'label' => 'Party Type', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['customer', 'supplier']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
            ['key' => 'party_reference', 'label' => 'Party Reference', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 128]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'warehouse_code', 'label' => 'Warehouse Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
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

        try {
            $this->openingBalances->addLine($row);

            return ImportRowResult::success(null);
        } catch (\Throwable $e) {
            return ImportRowResult::failure($e->getMessage());
        }
    }

    public function afterImport(ImportContext $context): void
    {
        $this->openingBalances->finalize($context);
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
