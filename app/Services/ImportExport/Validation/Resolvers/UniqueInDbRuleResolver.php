<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use App\Support\TenantImportScope;
use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class UniqueInDbRuleResolver implements RuleResolver
{
    /**
     * @param  array{table?: string, column?: string, scope?: string, except_on?: string, composite?: list<array{column: string, field: string}>}  $ruleDef
     */
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        if (
            in_array($context->mode, ['update', 'upsert'], true)
            && ($ruleDef['except_on'] ?? null) === 'update'
        ) {
            return [];
        }

        $table = (string) ($ruleDef['table'] ?? '');

        if ($table === '') {
            throw new InvalidArgumentException(
                'unique_in_db rule is missing a table. Re-open the import wizard validation step or reset rules for this column.',
            );
        }

        $column = (string) ($ruleDef['column'] ?? 'id');

        /** @var list<array{column: string, field: string}> $composite */
        $composite = $ruleDef['composite'] ?? [];
        $row = $rows[0] ?? [];

        $rule = Rule::unique($table, $column);

        if (($ruleDef['scope'] ?? null) === 'tenant') {
            $rule = TenantImportScope::constrainUnique($rule, $context->tenantId);
        }

        $siblingValues = [];
        foreach ($composite as $sibling) {
            $siblingColumn = (string) ($sibling['column'] ?? '');
            $siblingField = (string) ($sibling['field'] ?? '');

            if ($siblingColumn === '' || $siblingField === '') {
                continue;
            }

            $siblingValue = $row[$siblingField] ?? null;
            $siblingValues[] = $siblingValue;
            $rule = $rule->where($siblingColumn, $siblingValue);
        }

        $tracker = $context->duplicateTracker;

        $inFileDuplicateRule = function (string $attribute, mixed $value, Closure $fail) use (
            $table,
            $column,
            $siblingValues,
            $tracker,
        ): void {
            $key = implode('|', [$table, $column, ...array_map(
                static fn (mixed $v): string => $v === null ? '' : (string) $v,
                [$value, ...$siblingValues],
            )]);

            if ($tracker->isDuplicate($key)) {
                $fail('This row duplicates another row already present in this file.');
            }
        };

        return [$rule, $inFileDuplicateRule];
    }
}
