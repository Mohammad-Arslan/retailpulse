<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

/**
 * Sanitized validation contract for the import wizard — never includes table/column/index names.
 *
 * @phpstan-type ConstraintRule array{type: string, value?: int|string|list<string>, scope?: string, enforced_at?: string}
 * @phpstan-type FieldConstraints array{field: string, locked: true, rules: list<ConstraintRule>}
 */
final class ValidationConstraintDTO
{
    /**
     * @param  list<FieldConstraints>  $fields
     * @param  list<string>  $inexpressible  Opaque flags for parity tests (not shown to end users as schema names)
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $inexpressible = [],
    ) {}

    /**
     * @return array{fields: list<FieldConstraints>, inexpressible_count: int}
     */
    public function toArray(): array
    {
        return [
            'fields' => $this->fields,
            // Count only — never leak what could not be expressed as schema identifiers.
            'inexpressible_count' => count($this->inexpressible),
        ];
    }
}
