<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Employee self-service read/write wrappers.
 * Gated at route level by hr-module:employee_self_service.
 */
final class EmployeeSelfServiceService
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    public function resolveEmployeeForUser(User $user): Employee
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if ($employee === null) {
            throw new AuthorizationException('No Employee Record Is Linked To This User Account.');
        }

        return $employee;
    }

    /**
     * @return Collection<int, Payslip>
     */
    public function listOwnPayslips(User $user): Collection
    {
        $employee = $this->resolveEmployeeForUser($user);

        return Payslip::query()
            ->whereHas('payrollItem', fn ($query) => $query->where('employee_id', $employee->id))
            ->with([
                'payrollItem.payrollRun:id,payroll_number,period_start,period_end,currency_code,status',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function listOwnAttendance(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $employee = $this->resolveEmployeeForUser($user);

        return AttendanceRecord::query()
            ->with(['branch:id,name', 'source:id,name'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('clock_in')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, LeaveEntitlement>
     */
    public function listLeaveBalance(User $user): Collection
    {
        $employee = $this->resolveEmployeeForUser($user);

        return LeaveEntitlement::query()
            ->with(['leaveType:id,code,name,is_paid'])
            ->where('employee_id', $employee->id)
            ->orderBy('leave_type_id')
            ->get();
    }

    /**
     * @return Collection<int, LeaveRequest>
     */
    public function listOwnLeaveRequests(User $user): Collection
    {
        $employee = $this->resolveEmployeeForUser($user);

        return LeaveRequest::query()
            ->with(['leaveType:id,code,name'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->get();
    }

    public function requestLeave(
        User $user,
        LeaveType $leaveType,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?string $reason = null,
    ): LeaveRequest {
        $employee = $this->resolveEmployeeForUser($user);

        return $this->leaveService->requestLeave(
            $employee,
            $leaveType,
            $startDate,
            $endDate,
            $reason,
        );
    }
}
