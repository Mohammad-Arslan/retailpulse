<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\HolidayDate;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class HolidayCalendarExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new HolidayCalendarImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return HolidayDate::query()
            ->with(['calendar.legalEntity:id,legal_name', 'calendar.branch:id,code'])
            ->orderBy('holiday_date');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var HolidayDate $record */
        $calendar = $record->calendar;

        return [
            'calendar_code' => $calendar?->code ?? '',
            'calendar_name' => $calendar?->name ?? '',
            'legal_entity' => $calendar?->legalEntity?->legal_name ?? '',
            'branch_code' => $calendar?->branch?->code ?? '',
            'holiday_date' => $record->holiday_date?->toDateString() ?? '',
            'holiday_name' => $record->name,
            'holiday_type' => $record->holiday_type,
            'is_paid' => $record->is_paid ? 1 : 0,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
