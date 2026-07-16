<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Branch;
use App\Models\HolidayCalendar;
use App\Models\HolidayDate;
use App\Models\OrganizationEntity;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class HolidayCalendarImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'calendar_code', 'label' => 'Calendar Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'calendar_name', 'label' => 'Calendar Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'legal_entity', 'label' => 'Legal Entity', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'branch_code', 'label' => 'Branch Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'holiday_date', 'label' => 'Holiday Date', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'date']], 'default_transforms' => ['trim', 'date_normalize']],
            ['key' => 'holiday_name', 'label' => 'Holiday Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'holiday_type', 'label' => 'Holiday Type', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['public', 'optional', 'company']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
            ['key' => 'is_paid', 'label' => 'Is Paid', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']], 'default_transforms' => ['cast_bool']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row) {
            $calendarCode = (string) ($row['calendar_code'] ?? '');
            $calendar = HolidayCalendar::query()->where('code', $calendarCode)->first();

            if ($calendar === null) {
                $entityId = null;
                if (! empty($row['legal_entity'])) {
                    $entityId = OrganizationEntity::query()
                        ->where('legal_name', (string) $row['legal_entity'])
                        ->orWhere('tax_registration_no', (string) $row['legal_entity'])
                        ->value('id');
                }
                $branchId = null;
                if (! empty($row['branch_code'])) {
                    $branchId = Branch::query()->where('code', (string) $row['branch_code'])->value('id');
                }

                $calendar = HolidayCalendar::query()->create([
                    'code' => $calendarCode,
                    'name' => (string) ($row['calendar_name'] ?? $calendarCode),
                    'legal_entity_id' => $entityId,
                    'branch_id' => $branchId,
                    'status' => 'active',
                ]);
            }

            $date = (string) ($row['holiday_date'] ?? '');
            HolidayDate::query()->updateOrCreate(
                [
                    'holiday_calendar_id' => $calendar->id,
                    'holiday_date' => $date,
                ],
                [
                    'name' => (string) ($row['holiday_name'] ?? 'Holiday'),
                    'holiday_type' => $row['holiday_type'] ?? 'public',
                    'is_paid' => array_key_exists('is_paid', $row) ? (bool) $row['is_paid'] : true,
                ],
            );

            return ImportRowResult::success($calendar->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }
}
