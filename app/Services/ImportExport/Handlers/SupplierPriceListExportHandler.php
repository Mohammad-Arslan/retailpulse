<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\SupplierPriceListItem;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class SupplierPriceListExportHandler implements ExportHandler
{
    public function __construct(
        private readonly SupplierPriceListImportHandler $importHandler,
    ) {}

    public function columns(): array
    {
        return $this->importHandler->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = SupplierPriceListItem::query()
            ->with(['priceList.supplier', 'variant.product'])
            ->whereHas('priceList')
            ->orderBy('price_list_id')
            ->orderBy('id');

        $filters = $context->options['filters'] ?? [];

        if (! empty($filters['supplier_id'])) {
            $supplierId = (int) $filters['supplier_id'];
            $query->whereHas('priceList', fn (Builder $q) => $q->where('supplier_id', $supplierId));
        }

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereHas('priceList', fn (Builder $list) => $list->where('name', 'like', $term))
                    ->orWhereHas('priceList.supplier', fn (Builder $supplier) => $supplier
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term))
                    ->orWhereHas('variant', fn (Builder $variant) => $variant->where('sku', 'like', $term));
            });
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var SupplierPriceListItem $record */
        $list = $record->priceList;

        return [
            'supplier_code' => $list?->supplier?->code ?? '',
            'list_name' => $list?->name ?? '',
            'valid_from' => $list?->valid_from?->format('Y-m-d') ?? '',
            'valid_to' => $list?->valid_to?->format('Y-m-d') ?? '',
            'currency_code' => $list?->currency_code ?? '',
            'is_active' => $list?->is_active ? '1' : '0',
            'variant_sku' => $record->variant?->sku ?? '',
            'unit_price' => $record->unit_price,
            'min_qty' => $record->min_qty,
            'lead_time_days' => $record->lead_time_days ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
