<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

final class RuleMetaRegistry
{
    /**
     * @return list<array{rule: string, label: string, description: string, options: list<array{key: string, type: string, label: string, values?: list<string>}>}>
     */
    public static function allRuleMeta(): array
    {
        return [
            [
                'rule' => 'required',
                'label' => 'Required',
                'description' => 'Field must be present and not empty.',
                'options' => [],
            ],
            [
                'rule' => 'nullable',
                'label' => 'Nullable',
                'description' => 'Field may be empty.',
                'options' => [],
            ],
            [
                'rule' => 'string',
                'label' => 'String',
                'description' => 'Value must be a string.',
                'options' => [
                    ['key' => 'min', 'type' => 'number', 'label' => 'Minimum length'],
                    ['key' => 'max', 'type' => 'number', 'label' => 'Maximum length'],
                ],
            ],
            [
                'rule' => 'numeric',
                'label' => 'Numeric',
                'description' => 'Value must be numeric.',
                'options' => [
                    ['key' => 'min', 'type' => 'number', 'label' => 'Minimum value'],
                    ['key' => 'max', 'type' => 'number', 'label' => 'Maximum value'],
                ],
            ],
            [
                'rule' => 'decimal',
                'label' => 'Decimal',
                'description' => 'Value must be a decimal number.',
                'options' => [
                    ['key' => 'places', 'type' => 'number', 'label' => 'Decimal places'],
                ],
            ],
            [
                'rule' => 'email',
                'label' => 'Email',
                'description' => 'Value must be a valid email address.',
                'options' => [],
            ],
            [
                'rule' => 'boolean',
                'label' => 'Boolean',
                'description' => 'Value must be true or false.',
                'options' => [],
            ],
            [
                'rule' => 'date',
                'label' => 'Date',
                'description' => 'Value must be a valid date.',
                'options' => [
                    ['key' => 'format', 'type' => 'text', 'label' => 'Date format'],
                    ['key' => 'after', 'type' => 'text', 'label' => 'After date'],
                    ['key' => 'before', 'type' => 'text', 'label' => 'Before date'],
                ],
            ],
            [
                'rule' => 'in_list',
                'label' => 'In list',
                'description' => 'Value must be one of the allowed values.',
                'options' => [
                    ['key' => 'values', 'type' => 'tag_input', 'label' => 'Allowed values'],
                ],
            ],
            [
                'rule' => 'regex',
                'label' => 'Regex',
                'description' => 'Value must match the regular expression.',
                'options' => [
                    ['key' => 'pattern', 'type' => 'text', 'label' => 'Pattern'],
                ],
            ],
            [
                'rule' => 'exists_in_db',
                'label' => 'Exists in database',
                'description' => 'Value must exist in the specified table column.',
                'options' => [
                    ['key' => 'table', 'type' => 'db_table_picker', 'label' => 'Table'],
                    ['key' => 'column', 'type' => 'text', 'label' => 'Column'],
                    [
                        'key' => 'scope',
                        'type' => 'select',
                        'label' => 'Scope',
                        'values' => ['none', 'tenant'],
                    ],
                ],
            ],
            [
                'rule' => 'unique_in_db',
                'label' => 'Unique in database',
                'description' => 'Value must be unique in the specified table column.',
                'options' => [
                    ['key' => 'table', 'type' => 'db_table_picker', 'label' => 'Table'],
                    ['key' => 'column', 'type' => 'text', 'label' => 'Column'],
                    [
                        'key' => 'scope',
                        'type' => 'select',
                        'label' => 'Scope',
                        'values' => ['none', 'tenant'],
                    ],
                    [
                        'key' => 'except_on',
                        'type' => 'select',
                        'label' => 'Except on mode',
                        'values' => ['update'],
                    ],
                ],
            ],
        ];
    }
}
