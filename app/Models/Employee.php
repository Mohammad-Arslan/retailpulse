<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'employee_code',
    'user_id',
    'legal_entity_id',
    'primary_branch_id',
    'department_id',
    'designation_id',
    'grade_id',
    'reporting_manager_employee_id',
    'salary_structure_id',
    'hire_date',
    'termination_date',
    'probation_end_date',
    'confirmation_date',
    'contract_end_date',
    'employment_type',
    'joined_as',
    'default_cost_centre_id',
    'payment_method',
    'bank_details_encrypted',
    'status',
    'first_name',
    'middle_name',
    'last_name',
    'preferred_name',
    'title',
    'gender',
    'date_of_birth',
    'marital_status',
    'nationality',
    'national_id_encrypted',
    'email',
    'phone',
])]
class Employee extends Model
{
    use HasImages;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'contract_end_date' => 'date',
            'date_of_birth' => 'date',
            'bank_details_encrypted' => 'encrypted:array',
            'national_id_encrypted' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_employee_id');
    }

    public function defaultCostCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'default_cost_centre_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function medicalProfile(): HasOne
    {
        return $this->hasOne(EmployeeMedicalProfile::class);
    }

    public function shiftPreference(): HasOne
    {
        return $this->hasOne(EmployeeShiftPreference::class);
    }

    public function toilBalance(): HasOne
    {
        return $this->hasOne(ToilBalance::class);
    }

    public function toilLedgerEntries(): HasMany
    {
        return $this->hasMany(ToilLedgerEntry::class);
    }

    public function toilClaims(): HasMany
    {
        return $this->hasMany(ToilClaim::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class)->orderBy('sort_order');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeAttachment::class)->latest();
    }

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class);
    }

    public function holidayAssignments(): MorphMany
    {
        return $this->morphMany(HolidayCalendarAssignment::class, 'assignable');
    }

    public function fullName(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return trim(implode(' ', $parts));
    }
}
