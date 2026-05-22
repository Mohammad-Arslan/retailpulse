<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

final class ImportBehaviorMetaRegistry
{
    /**
     * @return list<array{key: string, label: string, description: string, default: bool, group: string}>
     */
    public static function allBehaviorMeta(): array
    {
        return [
            [
                'key' => 'strict',
                'label' => 'Stop on validation errors',
                'description' => 'Do not import any rows when validation errors are found. Generate an error report instead.',
                'default' => false,
                'group' => 'error_handling',
            ],
            [
                'key' => 'skip_invalid_rows',
                'label' => 'Skip invalid rows',
                'description' => 'Continue importing valid rows and skip rows that fail validation.',
                'default' => true,
                'group' => 'error_handling',
            ],
            [
                'key' => 'allow_partial_import',
                'label' => 'Allow partial import',
                'description' => 'Complete the import even when some rows fail during processing.',
                'default' => true,
                'group' => 'error_handling',
            ],
            [
                'key' => 'auto_trim',
                'label' => 'Auto-trim whitespace',
                'description' => 'Automatically trim leading and trailing spaces from all mapped columns.',
                'default' => true,
                'group' => 'transforms',
            ],
            [
                'key' => 'case_insensitive_match',
                'label' => 'Case-insensitive matching',
                'description' => 'Match existing records without regard to letter casing when updating.',
                'default' => false,
                'group' => 'matching',
            ],
            [
                'key' => 'duplicate_check',
                'label' => 'Duplicate checking',
                'description' => 'Enable unique-in-database validation rules to detect duplicate values.',
                'default' => true,
                'group' => 'validation',
            ],
        ];
    }
}
