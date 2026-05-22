<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Contracts;

use App\Services\ImportExport\ImportContext;
use Illuminate\Contracts\Validation\Rule;

interface RuleResolver
{
    /**
     * @param  array<string, mixed>  $ruleDef
     * @param  list<array<string, mixed>>|null  $rows
     * @return list<string|Rule>
     */
    public function resolve(array $ruleDef, ImportContext $context, ?array $rows = null): array;
}
