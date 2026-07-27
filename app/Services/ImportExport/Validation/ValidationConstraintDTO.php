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
     * @param  list<string>  $advisories  Sanitized, human-readable notes about constraints not
     *                                    expressed as a per-field locked rule (e.g. composite
     *                                    uniqueness) — plain English only, never schema identifiers.
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $inexpressible = [],
        public readonly array $advisories = [],
    ) {}

    /**
     * @return array{fields: list<FieldConstraints>, inexpressible_count: int, advisories: list<string>}
     */
    public function toArray(): array
    {
        return [
            'fields' => $this->fields,
            // Count only — never leak what could not be expressed as schema identifiers.
            'inexpressible_count' => count($this->inexpressible),
            'advisories' => $this->advisories,
        ];
    }
}
