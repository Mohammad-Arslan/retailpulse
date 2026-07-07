<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class CoaExportHandler implements ExportHandler
{
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

    public function query(ExportContext $context): Builder
    {
        return ChartOfAccount::query()->orderBy('code');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var ChartOfAccount $record */
        $parentCode = $record->parent_id !== null
            ? ChartOfAccount::query()->whereKey($record->parent_id)->value('code')
            : '';

        $branchCode = $record->branch_id !== null
            ? Branch::query()->whereKey($record->branch_id)->value('code')
            : '';

        return [
            'code' => $record->code,
            'name' => $record->name,
            'type' => $record->type->value,
            'parent_code' => $parentCode ?? '',
            'is_group' => $record->is_group ? 1 : 0,
            'is_postable' => $record->is_postable ? 1 : 0,
            'branch_code' => $branchCode ?? '',
            'currency_code' => $record->currency_code ?? '',
            'status' => $record->status,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
