<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Services\Accounting\CoaImportService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;

final class CoaImportHandler implements ImportHandler
{
    public function __construct(
        private readonly CoaImportService $coaImport,
    ) {}

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'type', 'label' => 'Type', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'in_list', 'values' => ['asset', 'liability', 'equity', 'revenue', 'expense']]], 'default_transforms' => ['trim', 'lowercase']],
            ['key' => 'parent_code', 'label' => 'Parent Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'is_group', 'label' => 'Group Account', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']], 'default_transforms' => ['cast_bool']],
            ['key' => 'is_postable', 'label' => 'Postable', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']], 'default_transforms' => ['cast_bool']],
            ['key' => 'branch_code', 'label' => 'Branch Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'currency_code', 'label' => 'Currency', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'size' => 3]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['active', 'inactive']]], 'default_transforms' => ['trim', 'lowercase']],
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
            $this->coaImport->stageLine($row);

            return ImportRowResult::success(null);
        } catch (\Throwable $e) {
            return ImportRowResult::failure($e->getMessage());
        }
    }

    public function afterImport(ImportContext $context): void
    {
        $this->coaImport->finalizeBatch($context);
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
