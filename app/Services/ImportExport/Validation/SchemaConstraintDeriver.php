<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

use App\Services\ImportExport\Contracts\ImportHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only import/export class that introspects physical schema.
 * Derives locked validation rules from columns/indexes and back-maps to logical CSV fields.
 */
final class SchemaConstraintDeriver
{
    /** @var array<string, array{dto: ValidationConstraintDTO, engine_by_field: array<string, list<array<string, mixed>>>, inexpressible: list<string>}> */
    private static array $cache = [];

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{dto: ValidationConstraintDTO, engine_by_field: array<string, list<array<string, mixed>>>, inexpressible: list<string>}
     */
    public function derive(ImportHandler $handler): array
    {
        $cacheKey = $handler::class;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $models = $handler->targetModels();
        $columnMap = $handler->columnMap();

        if ($models === [] || $columnMap === []) {
            $empty = [
                'dto' => new ValidationConstraintDTO([], [], $handler->compositeConstraintAdvisories()),
                'engine_by_field' => [],
                'inexpressible' => [],
            ];

            return self::$cache[$cacheKey] = $empty;
        }

        /** @var array<string, list<array{type: string, value?: mixed, scope?: string, enforced_at?: string}>> $dtoByField */
        $dtoByField = [];
        /** @var array<string, list<array<string, mixed>>> $engineByField */
        $engineByField = [];
        $inexpressible = [];
        /** @var list<string> $advisories */
        $advisories = [];

        $physicalToLogical = $this->invertColumnMap($columnMap, $models);
        $modelTables = $this->modelTables($models);

        foreach ($models as $modelClass) {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();
            $columns = Schema::getColumns($table);
            $indexes = Schema::getIndexes($table);

            foreach ($columns as $column) {
                $physical = (string) $column['name'];
                $logical = $physicalToLogical[$table.'.'.$physical] ?? $physicalToLogical[$physical] ?? null;

                if ($logical === null) {
                    continue;
                }

                if ($this->shouldSkipPhysicalColumn($physical)) {
                    continue;
                }

                $dtoRules = [];
                $engineRules = [];

                $nullable = (bool) ($column['nullable'] ?? true);
                $default = $column['default'] ?? null;
                $autoIncrement = (bool) ($column['auto_increment'] ?? false);

                if (! $nullable && $default === null && ! $autoIncrement) {
                    $dtoRules[] = ['type' => 'required'];
                    $engineRules[] = ['rule' => 'required'];
                }

                $typeRules = $this->typeRulesFromColumn($column);
                foreach ($typeRules['dto'] as $rule) {
                    $dtoRules[] = $rule;
                }
                foreach ($typeRules['engine'] as $rule) {
                    $engineRules[] = $rule;
                }

                if ($dtoRules !== []) {
                    $dtoByField[$logical] = array_merge($dtoByField[$logical] ?? [], $dtoRules);
                    $engineByField[$logical] = array_merge($engineByField[$logical] ?? [], $engineRules);
                }
            }

            foreach ($indexes as $index) {
                if (! ($index['unique'] ?? false) || ($index['primary'] ?? false)) {
                    continue;
                }

                $indexColumns = array_values(array_map('strval', $index['columns'] ?? []));
                $businessColumns = array_values(array_filter(
                    $indexColumns,
                    fn (string $col): bool => ! in_array($col, ['tenant_id', 'id'], true),
                ));

                if ($businessColumns === []) {
                    continue;
                }

                $tenantScoped = in_array('tenant_id', $indexColumns, true);

                if (count($businessColumns) > 1) {
                    $mapped = [];
                    foreach ($businessColumns as $physicalColumn) {
                        $logicalColumn = $physicalToLogical[$table.'.'.$physicalColumn] ?? $physicalToLogical[$physicalColumn] ?? null;

                        if ($logicalColumn === null) {
                            $mapped = null;

                            break;
                        }

                        $mapped[] = ['column' => $physicalColumn, 'field' => $logicalColumn];
                    }

                    if ($mapped === null) {
                        // Not every column maps to a logical CSV field (e.g. resolved
                        // through an FK lookup) — cannot be expressed as a locked
                        // rule against submitted row values.
                        $inexpressible[] = 'composite_unique:'.$table;

                        continue;
                    }

                    // Anchor the engine rule + advisory on the first mapped column;
                    // its siblings are enforced via additional ->where() constraints.
                    $anchor = $mapped[0];
                    $siblings = array_slice($mapped, 1);

                    $engineByField[$anchor['field']] = array_merge($engineByField[$anchor['field']] ?? [], [
                        [
                            'rule' => 'unique_in_db',
                            'table' => $table,
                            'column' => $anchor['column'],
                            'composite' => $siblings,
                            'scope' => $tenantScoped ? 'tenant' : null,
                            'except_on' => 'update',
                        ],
                    ]);

                    $labels = array_map(fn (array $m): string => $this->humanizeField($m['field']), $mapped);
                    $advisories[] = 'This import enforces uniqueness on '.implode(' + ', $labels)
                        .' — duplicate rows are rejected on import.';

                    continue;
                }

                $physical = $businessColumns[0];
                $logical = $physicalToLogical[$table.'.'.$physical] ?? $physicalToLogical[$physical] ?? null;

                if ($logical === null) {
                    $inexpressible[] = 'unmapped_unique:'.$table;

                    continue;
                }

                $scope = $tenantScoped ? 'tenant' : 'global';

                $dtoByField[$logical] = array_merge($dtoByField[$logical] ?? [], [
                    [
                        'type' => 'unique',
                        'scope' => $scope,
                        'enforced_at' => 'save',
                    ],
                ]);

                $engineByField[$logical] = array_merge($engineByField[$logical] ?? [], [
                    [
                        'rule' => 'unique_in_db',
                        'table' => $table,
                        'column' => $physical,
                        'scope' => $tenantScoped ? 'tenant' : null,
                        'except_on' => 'update',
                    ],
                ]);
            }
        }

        // Genuinely inexpressible constraints still get a sanitized, plain-English
        // advisory — either from the handler (it knows the domain semantics of its
        // own FK-resolved lookups) or a generic fallback that names no identifiers.
        if ($inexpressible !== []) {
            $handlerAdvisories = $handler->compositeConstraintAdvisories();

            if ($handlerAdvisories !== []) {
                array_push($advisories, ...$handlerAdvisories);
            } else {
                $advisories[] = 'This import enforces additional uniqueness constraints not shown above — some duplicate combinations may be rejected on save.';
            }
        }

        // Deduplicate rule types per field (keep first of each type; unique is distinct).
        $fields = [];
        foreach ($dtoByField as $field => $rules) {
            $fields[] = [
                'field' => $field,
                'locked' => true,
                'rules' => $this->dedupeDtoRules($rules),
            ];
        }

        usort($fields, fn (array $a, array $b): int => strcmp($a['field'], $b['field']));

        foreach ($engineByField as $field => $rules) {
            $engineByField[$field] = $this->dedupeEngineRules($rules);
        }

        unset($modelTables); // used for clarity in invert only

        $result = [
            'dto' => new ValidationConstraintDTO($fields, $inexpressible, $advisories),
            'engine_by_field' => $engineByField,
            'inexpressible' => $inexpressible,
        ];

        return self::$cache[$cacheKey] = $result;
    }

    public function dtoFor(ImportHandler $handler): ValidationConstraintDTO
    {
        return $this->derive($handler)['dto'];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function engineRulesByField(ImportHandler $handler): array
    {
        return $this->derive($handler)['engine_by_field'];
    }

    /**
     * Merge locked engine rules into a column_rules snapshot. Client removals/weakenings are ignored.
     *
     * @param  list<array<string, mixed>>  $columnRules
     * @return list<array<string, mixed>>
     */
    public function mergeLockedIntoColumnRules(ImportHandler $handler, array $columnRules): array
    {
        $locked = $this->engineRulesByField($handler);
        $dtoFields = collect($this->dtoFor($handler)->fields)->keyBy('field');

        $bySystemKey = [];
        foreach ($columnRules as $columnRule) {
            $key = (string) ($columnRule['system_key'] ?? '');
            if ($key === '') {
                // Fall back via handler column keys when older snapshots omit system_key.
                $key = (string) ($columnRule['mapped_to'] ?? $columnRule['column_key'] ?? '');
            }
            if ($key !== '' && isset($locked[$key])) {
                $bySystemKey[$key] = $columnRule;
            } elseif ($key !== '') {
                // Keep non-locked columns keyed for re-assembly; may not match locked logical fields.
                $bySystemKey[$key] = $columnRule;
            }
        }

        foreach ($locked as $logicalField => $engineRules) {
            $existing = null;
            foreach ($columnRules as $columnRule) {
                $systemKey = (string) ($columnRule['system_key'] ?? '');
                if ($systemKey === $logicalField) {
                    $existing = $columnRule;
                    break;
                }
            }
            if ($existing === null) {
                foreach ($columnRules as $columnRule) {
                    $candidate = (string) ($columnRule['mapped_to'] ?? $columnRule['column_key'] ?? '');
                    if ($candidate === $logicalField) {
                        $existing = $columnRule;
                        break;
                    }
                }
            }

            $existing ??= [
                'column_key' => $logicalField,
                'mapped_to' => $logicalField,
                'display_label' => $logicalField,
                'rules' => [],
                'is_required' => false,
                'default_value' => null,
                'transform' => [],
            ];

            $existing['system_key'] = $logicalField;
            $existing['rules'] = $this->mergeEngineRuleLists(
                is_array($existing['rules'] ?? null) ? $existing['rules'] : [],
                $engineRules,
            );

            $dto = $dtoFields->get($logicalField);
            if ($dto !== null) {
                foreach ($dto['rules'] as $rule) {
                    if (($rule['type'] ?? null) === 'required') {
                        $existing['is_required'] = true;
                    }
                }
            }

            $bySystemKey[$logicalField] = $existing;
        }

        // Preserve original order, then append any locked-only fields.
        $merged = [];
        $seen = [];
        foreach ($columnRules as $columnRule) {
            $key = (string) ($columnRule['system_key'] ?? $columnRule['mapped_to'] ?? $columnRule['column_key'] ?? '');
            if ($key !== '' && isset($bySystemKey[$key])) {
                $merged[] = $bySystemKey[$key];
                $seen[$key] = true;
                unset($bySystemKey[$key]);
            } else {
                $merged[] = $columnRule;
            }
        }

        foreach ($bySystemKey as $key => $columnRule) {
            if (! isset($seen[$key])) {
                $merged[] = $columnRule;
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, string>  $columnMap
     * @param  list<class-string<Model>>  $models
     * @return array<string, string> physical (or table.physical) → logical
     */
    private function invertColumnMap(array $columnMap, array $models): array
    {
        $primaryTable = null;
        if ($models !== []) {
            $primaryTable = (new $models[0])->getTable();
        }

        $inverted = [];

        foreach ($columnMap as $logical => $physicalSpec) {
            if (str_contains($physicalSpec, '.')) {
                [$modelOrTable, $column] = explode('.', $physicalSpec, 2);
                if (class_exists($modelOrTable) && is_subclass_of($modelOrTable, Model::class)) {
                    $table = (new $modelOrTable)->getTable();
                } else {
                    $table = $modelOrTable;
                }
                $inverted[$table.'.'.$column] = $logical;
                $inverted[$column] = $inverted[$column] ?? $logical;
            } else {
                if ($primaryTable !== null) {
                    $inverted[$primaryTable.'.'.$physicalSpec] = $logical;
                }
                $inverted[$physicalSpec] = $logical;
            }
        }

        return $inverted;
    }

    /**
     * @param  list<class-string<Model>>  $models
     * @return array<class-string<Model>, string>
     */
    private function modelTables(array $models): array
    {
        $tables = [];
        foreach ($models as $modelClass) {
            if (! is_subclass_of($modelClass, Model::class)) {
                throw new InvalidArgumentException("Invalid target model: {$modelClass}");
            }
            $tables[$modelClass] = (new $modelClass)->getTable();
        }

        return $tables;
    }

    /**
     * Human-readable label for a logical field, for advisory text only — never a
     * physical column/table/index name. E.g. 'product_variant_id' → 'Product Variant'.
     */
    private function humanizeField(string $field): string
    {
        $field = preg_replace('/_id$/', '', $field) ?? $field;

        return Str::of($field)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function shouldSkipPhysicalColumn(string $physical): bool
    {
        return in_array($physical, [
            'id',
            'tenant_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token',
        ], true);
    }

    /**
     * @param  array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, ...}  $column
     * @return array{dto: list<array<string, mixed>>, engine: list<array<string, mixed>>}
     */
    private function typeRulesFromColumn(array $column): array
    {
        $typeName = mb_strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
        $fullType = mb_strtolower((string) ($column['type'] ?? ''));
        $dto = [];
        $engine = [];

        if (str_contains($typeName, 'bool') || $typeName === 'tinyint' && str_contains($fullType, 'tinyint(1)')) {
            $dto[] = ['type' => 'boolean'];
            $engine[] = ['rule' => 'boolean'];

            return ['dto' => $dto, 'engine' => $engine];
        }

        if (preg_match('/^(date|datetime|timestamp)/', $typeName) === 1 || str_contains($fullType, 'date')) {
            $dto[] = ['type' => 'date'];
            $engine[] = ['rule' => 'date'];

            return ['dto' => $dto, 'engine' => $engine];
        }

        if (preg_match('/^(int|bigint|smallint|mediumint|tinyint)/', $typeName) === 1) {
            $dto[] = ['type' => 'integer'];
            $engine[] = ['rule' => 'numeric'];

            return ['dto' => $dto, 'engine' => $engine];
        }

        if (preg_match('/^(decimal|numeric|float|double|real)/', $typeName) === 1) {
            $dto[] = ['type' => 'numeric'];
            $engine[] = ['rule' => 'numeric'];

            return ['dto' => $dto, 'engine' => $engine];
        }

        // varchar / character / string / text
        if (
            str_contains($typeName, 'char')
            || str_contains($typeName, 'text')
            || str_contains($typeName, 'string')
            || $typeName === 'clob'
            || $typeName === ''
        ) {
            $dto[] = ['type' => 'string'];
            $engineRule = ['rule' => 'string'];

            $length = $this->extractLength($fullType, $column);
            if ($length !== null) {
                $dto[] = ['type' => 'max', 'value' => $length];
                $engineRule['max'] = $length;
            }

            $engine[] = $engineRule;

            return ['dto' => $dto, 'engine' => $engine];
        }

        if ($typeName === 'enum' || str_starts_with($fullType, 'enum(')) {
            $values = $this->extractEnumValues($fullType);
            if ($values !== []) {
                $dto[] = ['type' => 'in', 'value' => $values];
                $engine[] = ['rule' => 'in_list', 'values' => $values];
            }

            return ['dto' => $dto, 'engine' => $engine];
        }

        return ['dto' => $dto, 'engine' => $engine];
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function extractLength(string $fullType, array $column): ?int
    {
        if (preg_match('/(?:varchar|character varying|char|nvarchar)\s*\((\d+)\)/i', $fullType, $matches) === 1) {
            return (int) $matches[1];
        }

        // SQLite often reports type as "varchar" without length; Laravel schema may still expose length via type string.
        return null;
    }

    /**
     * @return list<string>
     */
    private function extractEnumValues(string $fullType): array
    {
        if (preg_match('/enum\((.+)\)/i', $fullType, $matches) !== 1) {
            return [];
        }

        preg_match_all("/'((?:\\\\'|[^'])*)'/", $matches[1], $valueMatches);

        return array_values($valueMatches[1] ?? []);
    }

    /**
     * @param  list<array{type: string, value?: mixed, scope?: string, enforced_at?: string}>  $rules
     * @return list<array{type: string, value?: mixed, scope?: string, enforced_at?: string}>
     */
    private function dedupeDtoRules(array $rules): array
    {
        $seen = [];
        $out = [];

        foreach ($rules as $rule) {
            $key = ($rule['type'] ?? '').':'.json_encode($rule['value'] ?? null).':'.($rule['scope'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $rule;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function dedupeEngineRules(array $rules): array
    {
        $seen = [];
        $out = [];

        foreach ($rules as $rule) {
            $name = (string) ($rule['rule'] ?? '');
            $key = $name === 'unique_in_db'
                ? $name.':'.($rule['table'] ?? '').':'.($rule['column'] ?? '')
                : $name;

            if (isset($seen[$key])) {
                // Prefer the richer definition (e.g. string with max over bare string).
                if ($name === 'string' && isset($rule['max'])) {
                    $out[$seen[$key]] = $rule;
                }

                continue;
            }

            $seen[$key] = count($out);
            $out[] = $rule;
        }

        return array_values($out);
    }

    /**
     * Locked rules win on conflict for the same rule name; custom rules of other types remain.
     *
     * @param  list<array<string, mixed>|string>  $custom
     * @param  list<array<string, mixed>>  $locked
     * @return list<array<string, mixed>|string>
     */
    private function mergeEngineRuleLists(array $custom, array $locked): array
    {
        $lockedNames = [];
        foreach ($locked as $rule) {
            $lockedNames[(string) ($rule['rule'] ?? '')] = true;
        }

        $merged = $locked;

        foreach ($custom as $rule) {
            $name = is_string($rule) ? $rule : (string) ($rule['rule'] ?? '');

            // Allow additive custom rules (e.g. regex, stricter max as separate string rule with max).
            // Do not allow removing locked rule names — skip custom duplicates of locked names
            // except when custom is a stricter string max (both apply via engine merge of same field).
            if ($name !== '' && isset($lockedNames[$name]) && $name !== 'string' && $name !== 'numeric') {
                continue;
            }

            // Stricter custom max on top of locked max: keep both string rules — DynamicRuleEngine merges them.
            $merged[] = $rule;
        }

        return array_values($merged);
    }
}
