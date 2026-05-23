<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\StockMovement;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use App\Services\ImportExport\Support\InventoryImportSupport;
use App\Support\TenantImportScope;
use Illuminate\Database\Eloquent\Builder;

final class InventoryAdjustmentExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return InventoryImportSupport::adjustmentColumns();
    }

    public function query(ExportContext $context): Builder
    {
        return StockMovement::query()
            ->with(['warehouse.branch', 'variant', 'batch'])
            ->whereIn('reason', ['adjustment', 'damaged'])
            ->whereHas('warehouse.branch', fn ($q) => TenantImportScope::constrain($q, $context->tenantId));
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var StockMovement $record */
        return [
            'warehouse_code' => $record->warehouse?->code ?? '',
            'sku' => $record->variant?->sku ?? '',
            'batch_no' => $record->batch?->batch_no ?? '',
            'reason' => $record->reason->value,
            'qty_delta' => $record->qty_delta,
            'notes' => $record->notes ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
