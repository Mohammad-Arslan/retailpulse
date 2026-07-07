<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ChartOfAccountType;
use App\Models\Branch;
use App\Models\ChartOfAccount;

final class CoaImportService
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertByCode(array $row, int $userId): ChartOfAccount
    {
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $typeValue = strtolower(trim((string) ($row['type'] ?? '')));

        if ($code === '' || $name === '' || $typeValue === '') {
            throw new \InvalidArgumentException('Code, name, and type are required.');
        }

        if (! in_array($typeValue, ChartOfAccountType::values(), true)) {
            throw new \InvalidArgumentException('Invalid account type.');
        }

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
