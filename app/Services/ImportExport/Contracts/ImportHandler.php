<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Contracts;

use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Database\Eloquent\Model;

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
