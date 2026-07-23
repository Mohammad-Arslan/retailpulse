<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Contracts;

use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

interface ExportHandler
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public function columns(): array;

    /**
     * @return Builder<Model>|LazyCollection<int, mixed>
     */
    public function query(ExportContext $context): Builder|LazyCollection;

    /**
     * @return array<string, mixed>
     */
    public function map(mixed $record, ExportContext $context): array;

    public function chunkSize(): int;
}
