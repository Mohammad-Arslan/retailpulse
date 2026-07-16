<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

final class EmployeeAssignmentService
{
    /**
     * @var list<string>
     */
    public const TRACKED_FIELDS = [
        'department_id',
        'designation_id',
        'grade_id',
        'primary_branch_id',
        'salary_structure_id',
    ];

    /**
     * Apply org field changes, optionally effective-dated.
     *
     * @param  array<string, mixed>  $attributes  Employee update attributes (by reference)
     * @param  array<string, string|null>  $effectiveFrom  field => Y-m-d or null for immediate
     */
    public function applyOrgChanges(Employee $employee, array &$attributes, array $effectiveFrom = []): void
    {
        $today = now()->toDateString();
        $changedBy = (int) (Auth::id() ?? 0);

        foreach (self::TRACKED_FIELDS as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $new = $attributes[$field] !== null && $attributes[$field] !== ''
                ? (int) $attributes[$field]
                : null;
            $old = $employee->{$field} !== null ? (int) $employee->{$field} : null;

            if ($old === $new) {
                unset($attributes[$field]);

                continue;
            }

            $from = $effectiveFrom[$field] ?? $today;
            if ($from > $today) {
                $this->scheduleChange($employee, $field, $new, $from, $changedBy);
                unset($attributes[$field]);
            } else {
                $this->recordImmediateChange($employee, $field, $old, $new, $from, $changedBy);
            }
        }
    }

    public function scheduleChange(
        Employee $employee,
        string $field,
        ?int $newValue,
        string $effectiveFrom,
        int $changedBy,
    ): void {
        EmployeeAssignmentHistory::query()->create([
            'employee_id' => $employee->id,
            'field_name' => $field,
            'old_value' => $employee->{$field} !== null ? (string) $employee->{$field} : null,
            'new_value' => $newValue !== null ? (string) $newValue : null,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'changed_by' => $changedBy ?: null,
        ]);
    }

    public function recordImmediateChange(
        Employee $employee,
        string $field,
        ?int $oldValue,
        ?int $newValue,
        string $effectiveFrom,
        int $changedBy,
    ): void {
        EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('field_name', $field)
            ->whereNull('effective_to')
            ->update(['effective_to' => $effectiveFrom]);

        EmployeeAssignmentHistory::query()->create([
            'employee_id' => $employee->id,
            'field_name' => $field,
            'old_value' => $oldValue !== null ? (string) $oldValue : null,
            'new_value' => $newValue !== null ? (string) $newValue : null,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'changed_by' => $changedBy ?: null,
        ]);
    }

    public function applyDueScheduledChanges(Employee $employee): void
    {
        $today = now()->toDateString();

        $pending = EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $today)
            ->whereNull('effective_to')
            ->whereIn('field_name', self::TRACKED_FIELDS)
            ->orderBy('effective_from')
            ->get()
            ->groupBy('field_name');

        foreach ($pending as $field => $rows) {
            /** @var EmployeeAssignmentHistory|null $latest */
            $latest = $rows->sortByDesc('effective_from')->first();
            if ($latest === null) {
                continue;
            }

            $current = $employee->{$field} !== null ? (string) $employee->{$field} : null;
            if ($current === $latest->new_value) {
                continue;
            }

            $employee->{$field} = $latest->new_value !== null ? (int) $latest->new_value : null;
            $employee->save();
        }
    }

    public function resolveValueAt(Employee $employee, string $field, CarbonInterface $date): ?int
    {
        $history = EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('field_name', $field)
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($history !== null && $history->new_value !== null && $history->new_value !== '') {
            return (int) $history->new_value;
        }

        $current = $employee->{$field};

        return $current !== null ? (int) $current : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForEmployee(Employee $employee): array
    {
        return EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeAssignmentHistory $row): array => [
                'id' => $row->id,
                'field_name' => $row->field_name,
                'old_value' => $row->old_value,
                'new_value' => $row->new_value,
                'effective_from' => $row->effective_from?->toDateString(),
                'effective_to' => $row->effective_to?->toDateString(),
                'changed_by' => $row->changed_by,
            ])
            ->all();
    }
}
