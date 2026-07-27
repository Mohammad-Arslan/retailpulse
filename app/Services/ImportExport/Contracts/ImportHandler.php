<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Contracts;

use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\Validation\SchemaConstraintDeriver;
use Illuminate\Database\Eloquent\Model;

/**
 * A row's own effects and its ImportRowSuccess bookkeeping marker are written in the
 * same DB transaction (see ProcessImportJob::processRowWithIsolation), so a given row
 * is processed at most once across the life of a job — including retries after the
 * worker dies mid-chunk (tries = 3). processRow() therefore does not need to guard
 * against being replayed for a row it already succeeded on in an earlier attempt.
 *
 * That guarantee is per-row, not per *value*: two different rows that happen to
 * describe the same natural key (e.g. the same product code twice in one file) are
 * still two separate calls to processRow(). Handlers that create rather than upsert
 * must still detect and reject that case themselves (see BrandImportHandler's
 * find-by-natural-key-before-create pattern) — this contract does not deduplicate
 * on content, only on (job, row index).
 */
interface ImportHandler
{
    /**
     * @return list<array{key: string, label: string, required: bool, default_rules: array<int, array<string, mixed>>, default_transforms: array<int, string|array<string, mixed>>}>
     */
    public function columns(): array;

    /**
     * Eloquent models this handler writes. Used only for schema-derived locked rules.
     *
     * @return list<class-string<Model>>
     */
    public function targetModels(): array;

    /**
     * Logical CSV/system field → physical DB column on a target model.
     * Use "ModelClass.column" when the field belongs to a non-primary target model.
     * Omit lookup-only fields (e.g. category_code → resolves to category_id) when the
     * file never contains the physical column value.
     *
     * @return array<string, string>
     */
    public function columnMap(): array;

    /**
     * Sanitized, plain-English advisories for constraints {@see SchemaConstraintDeriver}
     * cannot express as a locked rule (e.g. composite uniqueness resolved through an
     * FK lookup, like inventory's warehouse+variant+batch). Only the handler knows
     * the domain semantics of its own lookup columns — never table/column/index names.
     *
     * @return list<string>
     */
    public function compositeConstraintAdvisories(): array;

    /**
     * True when this handler only ever inserts new records — a re-import in the
     * default "create" mode rejects every row that already exists rather than
     * updating it. Drives an explanatory notice in the import wizard; handlers
     * that support a "replace" mode should branch on {@see ImportContext::$mode}
     * themselves in processRow()/validateRow() (e.g. InventoryImportHandler).
     */
    public function isInsertOnly(): bool;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, list<string>>
     */
    public function validateRow(array $row, ImportContext $context): array;

    /**
     * @param  array<string, mixed>  $row
     */
    public function processRow(array $row, ImportContext $context): ImportRowResult;

    public function afterImport(ImportContext $context): void;

    public function chunkSize(): int;
}
