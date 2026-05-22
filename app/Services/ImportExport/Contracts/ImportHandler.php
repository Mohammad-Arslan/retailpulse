<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Contracts;

use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;

interface ImportHandler
{
    /**
     * @return list<array{key: string, label: string, required: bool, default_rules: array<int, array<string, mixed>>, default_transforms: array<int, string|array<string, mixed>>}>
     */
    public function columns(): array;

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
