<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use App\Support\TenantImportScope;
use Illuminate\Validation\Rule;

final class ExistsInDbRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        $table = (string) ($ruleDef['table'] ?? '');
        $column = (string) ($ruleDef['column'] ?? 'id');

        if ($table === '') {
            throw new \InvalidArgumentException(
                'exists_in_db rule is missing a table. Re-open the import wizard validation step or reset rules for this column.',
            );
        }

        $rule = Rule::exists($table, $column);

        if (($ruleDef['scope'] ?? null) === 'tenant') {
            $rule = TenantImportScope::constrainExists($rule, $context->tenantId);
        }

        return [$rule];
    }
}
