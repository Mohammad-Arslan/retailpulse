<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseApprovalPolicy;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpenseOccurrence;
use App\Models\RecurringExpenseSchedule;
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
