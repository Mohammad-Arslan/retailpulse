<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingImportBatchStatus;
use App\Enums\ChartOfAccountType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CoaImportBatch;
use App\Models\CoaImportLine;
use App\Models\ImportExportJob;
use App\Services\ImportExport\ImportContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CoaImportService
{
    /** @var list<array<string, mixed>> */
    private array $pendingRows = [];

    public function reset(): void
    {
        $this->pendingRows = [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function stageLine(array $row): void
    {
        $errors = $this->validateCoaLine($row);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $this->pendingRows[] = $row;
    }

    public function finalizeBatch(ImportContext $context): void
    {
        if ($context->isDryRun || $this->pendingRows === []) {
            $this->reset();

            return;
        }

        $job = ImportExportJob::query()->findOrFail($context->jobId);
        $validRows = 0;
        $errorRows = 0;

        DB::transaction(function () use ($context, $job, &$validRows, &$errorRows) {
            $batch = CoaImportBatch::query()->create([
                'file_name' => (string) ($job->original_filename ?? 'coa-import.csv'),
                'imported_by' => $context->userId,
                'status' => AccountingImportBatchStatus::Pending,
                'validation_summary' => [],
            ]);

            foreach ($this->pendingRows as $row) {
                $errors = $this->validateCoaLine($row);
                $isValid = $errors === [];

                if ($isValid) {
                    $validRows++;
                } else {
                    $errorRows++;
                }

                CoaImportLine::query()->create([
                    'coa_import_batch_id' => $batch->id,
                    'code' => strtoupper(trim((string) $row['code'])),
                    'name' => trim((string) $row['name']),
                    'type' => strtolower(trim((string) $row['type'])),
                    'parent_code' => $this->nullableCode($row['parent_code'] ?? null),
                    'is_group' => filter_var($row['is_group'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_postable' => filter_var($row['is_postable'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'branch_code' => $this->nullableCode($row['branch_code'] ?? null),
                    'currency_code' => $this->nullableCurrency($row['currency_code'] ?? null),
                    'status' => strtolower(trim((string) ($row['status'] ?? 'active'))) ?: 'active',
                    'validation_status' => $isValid ? 'valid' : 'invalid',
                    'validation_message' => $isValid ? null : implode(' ', $errors),
                ]);
            }

            $batch->update([
                'status' => $errorRows === 0 ? AccountingImportBatchStatus::Validated : AccountingImportBatchStatus::Failed,
                'validation_summary' => [
                    'total_rows' => count($this->pendingRows),
                    'valid_rows' => $validRows,
                    'error_rows' => $errorRows,
                ],
            ]);
        });

        $this->reset();
    }

    public function approveBatch(CoaImportBatch $batch, int $userId): CoaImportBatch
    {
        if ($batch->status !== AccountingImportBatchStatus::Validated) {
            throw new DomainException('Only validated COA import batches can be approved.');
        }

        $batch->loadMissing('lines');

        return DB::transaction(function () use ($batch, $userId) {
            foreach ($batch->lines as $line) {
                if ($line->validation_status !== 'valid') {
                    continue;
                }

                $this->upsertByCode([
                    'code' => $line->code,
                    'name' => $line->name,
                    'type' => $line->type,
                    'parent_code' => $line->parent_code,
                    'is_group' => $line->is_group,
                    'is_postable' => $line->is_postable,
                    'branch_code' => $line->branch_code,
                    'currency_code' => $line->currency_code,
                    'status' => $line->status,
                ], $userId);
            }

            $batch->update([
                'status' => AccountingImportBatchStatus::Completed,
                'approved_by' => $userId,
            ]);

            return $batch->fresh('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertByCode(array $row, int $userId): ChartOfAccount
    {
        $errors = $this->validateCoaLine($row);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $code = strtoupper(trim((string) $row['code']));
        $name = trim((string) $row['name']);
        $typeValue = strtolower(trim((string) $row['type']));
        $parentId = $this->resolveParentId($row['parent_code'] ?? null);
        $branchId = $this->resolveBranchId($row['branch_code'] ?? null);

        $attributes = [
            'name' => $name,
            'type' => $typeValue,
            'parent_id' => $parentId,
            'account_level' => $parentId !== null
                ? ((int) ChartOfAccount::query()->whereKey($parentId)->value('account_level')) + 1
                : 1,
            'is_group' => filter_var($row['is_group'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_postable' => filter_var($row['is_postable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'branch_id' => $branchId,
            'currency_code' => isset($row['currency_code']) && $row['currency_code'] !== ''
                ? strtoupper(trim((string) $row['currency_code']))
                : null,
            'status' => strtolower(trim((string) ($row['status'] ?? 'active'))) ?: 'active',
            'updated_by' => $userId,
        ];

        $account = ChartOfAccount::query()->where('code', $code)->first();

        if ($account === null) {
            return ChartOfAccount::query()->create([
                ...$attributes,
                'code' => $code,
                'created_by' => $userId,
            ]);
        }

        $account->update($attributes);

        return $account->fresh();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function validateCoaLine(array $row): array
    {
        $errors = [];
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $typeValue = strtolower(trim((string) ($row['type'] ?? '')));

        if ($code === '') {
            $errors[] = 'Code is required.';
        } elseif (strlen($code) > 32) {
            $errors[] = 'Code must not exceed 32 characters.';
        }

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if ($typeValue === '' || ! in_array($typeValue, ChartOfAccountType::values(), true)) {
            $errors[] = 'Invalid account type.';
        }

        if (! empty($row['parent_code'])) {
            $parent = ChartOfAccount::query()
                ->where('code', strtoupper(trim((string) $row['parent_code'])))
                ->first();

            if ($parent === null) {
                $errors[] = "Parent account '{$row['parent_code']}' not found.";
            } elseif (! $parent->is_group) {
                $errors[] = "Parent account '{$row['parent_code']}' must be a group account.";
            }
        }

        if (! empty($row['branch_code'])) {
            $branchExists = Branch::query()
                ->where('code', strtoupper(trim((string) $row['branch_code'])))
                ->exists();

            if (! $branchExists) {
                $errors[] = "Branch code '{$row['branch_code']}' not found.";
            }
        }

        return $errors;
    }

    private function nullableCode(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }

    private function nullableCurrency(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }

    private function resolveParentId(mixed $parentCode): ?int
    {
        if ($parentCode === null || trim((string) $parentCode) === '') {
            return null;
        }

        $parent = ChartOfAccount::query()
            ->where('code', strtoupper(trim((string) $parentCode)))
            ->first();

        if ($parent === null) {
            throw new \InvalidArgumentException('Parent account code not found: '.$parentCode);
        }

        return $parent->id;
    }

    private function resolveBranchId(mixed $branchCode): ?int
    {
        if ($branchCode === null || trim((string) $branchCode) === '') {
            return null;
        }

        $branch = Branch::query()
            ->where('code', strtoupper(trim((string) $branchCode)))
            ->first();

        if ($branch === null) {
            throw new \InvalidArgumentException('Branch code not found: '.$branchCode);
        }

        return $branch->id;
    }
}
