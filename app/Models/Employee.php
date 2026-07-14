<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_code',
    'user_id',
    'legal_entity_id',
    'primary_branch_id',
    'salary_structure_id',
    'hire_date',
    'termination_date',
    'employment_type',
    'default_cost_centre_id',
    'payment_method',
    'bank_details_encrypted',
    'status',
    'first_name',
    'last_name',
    'email',
    'phone',
])]
class Employee extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'bank_details_encrypted' => 'encrypted:array',
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

    public function defaultCostCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'default_cost_centre_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
