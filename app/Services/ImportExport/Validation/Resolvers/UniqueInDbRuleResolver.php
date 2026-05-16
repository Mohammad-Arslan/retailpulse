<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use Illuminate\Validation\Rule;

final class UniqueInDbRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        if (
            in_array($context->mode, ['update', 'upsert'], true)
            && ($ruleDef['except_on'] ?? null) === 'update'
        ) {
            return [];
        }

        $table = (string) ($ruleDef['table'] ?? '');
        $column = (string) ($ruleDef['column'] ?? 'id');

        $rule = Rule::unique($table, $column);

        if (($ruleDef['scope'] ?? null) === 'tenant') {
            $rule = $rule->where('tenant_id', $context->tenantId);
        }

        return [$rule];
    }
}
