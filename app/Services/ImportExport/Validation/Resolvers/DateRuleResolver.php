<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation\Resolvers;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\ImportContext;

final class DateRuleResolver implements RuleResolver
{
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array
    {
        $rules = ['date'];

        if (isset($ruleDef['format'])) {
            $rules[] = 'date_format:'.$ruleDef['format'];
        }

        if (isset($ruleDef['after'])) {
            $rules[] = 'after:'.$ruleDef['after'];
        }

        if (isset($ruleDef['before'])) {
            $rules[] = 'before:'.$ruleDef['before'];
        }

        return $rules;
    }
}
