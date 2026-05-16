<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use Illuminate\Validation\Rule;

final class ExistsInDbRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        $table = (string) ($ruleDef['table'] ?? '');
        $column = (string) ($ruleDef['column'] ?? 'id');

        $rule = Rule::exists($table, $column);

        if (($ruleDef['scope'] ?? null) === 'tenant') {
            $rule = $rule->where('tenant_id', $context->tenantId);
        }

        return [$rule];
    }
}
