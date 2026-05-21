<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Inventory;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use App\Support\TenantImportScope;
use Illuminate\Database\Eloquent\Builder;

final class InventoryExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new InventoryImportHandler(app(\App\Services\InventoryService::class)))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Inventory::query()
            ->with(['warehouse.branch', 'variant.product', 'batch'])
            ->whereHas('warehouse.branch', fn ($q) => TenantImportScope::constrain($q, $context->tenantId));
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Inventory $record */
        return [
            'warehouse_code' => $record->warehouse?->code ?? '',
            'sku' => $record->variant?->sku ?? '',
            'qty' => $record->quantity_on_hand,
            'batch_no' => $record->batch?->batch_no ?? '',
            'expiry_date' => $record->batch?->expiry_date?->format('Y-m-d') ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
