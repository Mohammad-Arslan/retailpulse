<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Supplier;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class SupplierExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'tax_registration_no', 'label' => 'Tax Registration No'],
            ['key' => 'currency_code', 'label' => 'Currency'],
            ['key' => 'balance', 'label' => 'Balance'],
            ['key' => 'is_active', 'label' => 'Active'],
        ];
    }

    public function query(ExportContext $context): Builder
    {
        return Supplier::query()->orderBy('name');
    }

    public function mapRecord(object $record): array
    {
        /** @var Supplier $record */
        return [
            'code' => $record->code,
            'name' => $record->name,
            'email' => $record->email,
            'phone' => $record->phone,
            'tax_registration_no' => $record->tax_registration_no,
            'currency_code' => $record->currency_code,
            'balance' => $record->balance,
            'is_active' => $record->is_active ? '1' : '0',
        ];
    }
}
