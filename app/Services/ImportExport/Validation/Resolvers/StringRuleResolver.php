<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;

final class StringRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        $rules = ['string'];

        if (isset($ruleDef['min'])) {
            $rules[] = 'min:'.$ruleDef['min'];
        }

        if (isset($ruleDef['max'])) {
            $rules[] = 'max:'.$ruleDef['max'];
        }

        return $rules;
    }
}
