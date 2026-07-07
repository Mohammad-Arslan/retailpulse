<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Enums\AccountingImportBatchStatus;
use App\Models\CoaImportBatch;
use App\Models\ImportExportJob;
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
            $account = $this->coaImport->upsertByCode($row, $context->userId);

            return ImportRowResult::success($account->id);
        } catch (\Throwable $e) {
            return ImportRowResult::failure($e->getMessage());
        }
    }

    public function afterImport(ImportContext $context): void
    {
        if ($context->isDryRun) {
            return;
        }

        $job = ImportExportJob::query()->find($context->jobId);

        if ($job === null) {
            return;
        }

        CoaImportBatch::query()->create([
            'file_name' => (string) ($job->original_filename ?? 'coa-import.csv'),
            'imported_by' => $context->userId,
            'status' => AccountingImportBatchStatus::Completed,
            'validation_summary' => [
                'success_rows' => (int) $job->success_rows,
                'failed_rows' => (int) $job->failed_rows,
                'skipped_rows' => (int) $job->skipped_rows,
            ],
        ]);
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
