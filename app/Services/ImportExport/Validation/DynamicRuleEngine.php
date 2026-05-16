<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

use App\Services\ImportExport\ImportContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

final class DynamicRuleEngine
{
    public function __construct(
        private readonly RuleResolverRegistry $registry,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $columnRules
     */
    public function buildValidator(array $rows, array $columnRules, ImportContext $context): Validator
    {
        $rules = [];

        foreach ($columnRules as $column) {
            $field = (string) ($column['column_key'] ?? '');
            if ($field === '') {
                continue;
            }

            $fieldRules = [];
            $ruleDefs = $column['rules'] ?? [];

            foreach ($ruleDefs as $ruleDef) {
                $ruleName = is_string($ruleDef) ? $ruleDef : ($ruleDef['rule'] ?? null);
                if ($ruleName === null) {
                    continue;
                }

                $def = is_array($ruleDef) ? $ruleDef : ['rule' => $ruleName];
                $resolved = $this->registry->get($ruleName)->resolve($def, $context, $rows);
                $fieldRules = array_merge($fieldRules, $resolved);
            }

            if (($column['is_required'] ?? false) && ! in_array('nullable', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            }

            $rules["rows.*.{$field}"] = $fieldRules;
        }

        return ValidatorFacade::make(['rows' => $rows], $rules);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $columnRules
     * @return array<string, mixed>
     */
    public function applyTransforms(array $row, array $columnRules): array
    {
        foreach ($columnRules as $column) {
            $key = (string) ($column['column_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $value = $row[$key] ?? null;

            if (($value === null || $value === '') && isset($column['default_value'])) {
                $value = $column['default_value'];
            }

            $transforms = $column['transform'] ?? [];
            if (is_string($transforms)) {
                $transforms = [$transforms];
            }

            foreach ($transforms as $transform) {
                $value = TransformPipeline::apply($transform, $value);
            }

            $row[$key] = $value;
        }

        return $row;
    }
}
