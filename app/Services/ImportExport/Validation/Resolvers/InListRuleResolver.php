<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;
use Illuminate\Validation\Rule;

final class InListRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        /** @var list<string> $values */
        $values = $ruleDef['values'] ?? [];

        return [Rule::in($values)];
    }
}
