<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRecord;
use App\Services\Overtime\OvertimeEngine;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class OvertimeRecordController extends Controller
{
    public function __construct(
        private readonly OvertimeEngine $overtimeEngine,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OvertimeRecord::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = OvertimeRecord::query()
            ->with([
                'employee:id,first_name,last_name,employee_code',
                'policy:id,daily_threshold_minutes',
            ])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'date', $filters['direction'] ?? 'desc');

        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Overtime/Records/Index', [
            'records' => $records->through(fn (OvertimeRecord $record) => [
                'id' => $record->id,
                'employee' => $record->employee?->fullName(),
                'employee_code' => $record->employee?->employee_code,
                'date' => $record->date?->toDateString(),
                'regular_minutes' => $record->regular_minutes,
                'overtime_minutes' => $record->overtime_minutes,
                'day_type' => $record->day_type,
                'resolved_multiplier' => (string) $record->resolved_multiplier,
                'pay_units' => $this->overtimeEngine->calculatePayUnits($record),
                'status' => $record->status,
            ]),
            'filters' => $filters,
        ]);
    }

    public function approve(Request $request, OvertimeRecord $overtimeRecord): RedirectResponse
    {
        $this->authorize('approve', $overtimeRecord);

        try {
            $this->overtimeEngine->approveRecord($overtimeRecord, (int) $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Overtime Record Approved Successfully.'));
    }

    public function reject(Request $request, OvertimeRecord $overtimeRecord): RedirectResponse
    {
        $this->authorize('reject', $overtimeRecord);

        try {
            $this->overtimeEngine->rejectRecord($overtimeRecord, (int) $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Overtime Record Rejected Successfully.'));
    }
}
