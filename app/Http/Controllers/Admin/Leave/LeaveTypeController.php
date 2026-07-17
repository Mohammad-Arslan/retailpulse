<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\StoreLeaveTypeRequest;
use App\Http\Requests\Admin\Leave\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LeaveTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveType::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = LeaveType::query()
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc');

        $types = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Leave/Types/Index', [
            'types' => $types->through(fn (LeaveType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'is_paid' => $type->is_paid,
                'affects_payroll' => $type->affects_payroll,
                'payroll_deduction_component_code' => $type->payroll_deduction_component_code,
                'payroll_encashment_component_code' => $type->payroll_encashment_component_code,
                'status' => $type->status,
            ]),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveType::class);

        LeaveType::query()->create($request->validated());

        return back()->with('success', __('Leave Type Created Successfully.'));
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $this->authorize('update', $leaveType);

        $leaveType->update($request->validated());

        return back()->with('success', __('Leave Type Updated Successfully.'));
    }
}
