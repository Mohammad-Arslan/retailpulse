<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use Illuminate\Validation\Rule;

final class DecimalRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        $places = (int) ($ruleDef['places'] ?? 2);

        return [
            'numeric',
            Rule::decimal(0, $places),
        ];
    }
}
