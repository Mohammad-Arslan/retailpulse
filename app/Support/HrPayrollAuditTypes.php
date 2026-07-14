<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseApprovalPolicy;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeMultiplier;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use App\Models\PayComponent;
use App\Models\PayrollApprovalSetting;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Models\RecurringExpenseOccurrence;
use App\Models\RecurringExpenseSchedule;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\StatutoryScheme;
use App\Models\TaxSlab;
use Illuminate\Database\Eloquent\Model;

final class HrPayrollAuditTypes
{
    /**
     * @var list<class-string<Model>>
     */
    private const TYPES = [
        Employee::class,
        BranchHrProfile::class,
        Expense::class,
        ExpenseCategory::class,
        ExpenseApprovalPolicy::class,
        ExpenseAttachment::class,
        RecurringExpenseSchedule::class,
        RecurringExpenseOccurrence::class,
        AttendanceSource::class,
        AttendanceRecord::class,
        LeaveType::class,
        LeavePolicy::class,
        LeaveEntitlement::class,
        LeaveRequest::class,
        OvertimePolicy::class,
        OvertimeMultiplier::class,
        OvertimeRecord::class,
        PayComponent::class,
        SalaryStructure::class,
        SalaryStructureComponent::class,
        TaxSlab::class,
        StatutoryScheme::class,
        PayrollRun::class,
        PayrollItem::class,
        PayrollItemLine::class,
        PayrollApprovalSetting::class,
    ];

    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        return self::TYPES;
    }

    public static function includes(Model $model): bool
    {
        return in_array($model::class, self::TYPES, true);
    }
}
