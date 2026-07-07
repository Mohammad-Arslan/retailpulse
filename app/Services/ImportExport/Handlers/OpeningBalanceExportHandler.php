<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\JournalTransaction;
use App\Services\Accounting\OpeningBalanceImportService;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class OpeningBalanceExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new OpeningBalanceImportHandler(app(OpeningBalanceImportService::class)))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return JournalTransaction::query()
            ->whereHas('journalEntry', fn (Builder $query) => $query
                ->where('is_opening_balance', true)
                ->where('status', 'posted'))
            ->with(['account:id,code', 'journalEntry:id,journal_number'])
            ->orderBy('journal_entry_id')
            ->orderBy('line_sequence');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var JournalTransaction $record */
        return [
            'account_code' => $record->account?->code ?? '',
            'debit' => (float) $record->debit,
            'credit' => (float) $record->credit,
            'description' => $record->description ?? '',
            'party_type' => '',
            'party_reference' => '',
            'warehouse_code' => '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
