<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;

final class BooleanRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        return ['boolean'];
    }
}
