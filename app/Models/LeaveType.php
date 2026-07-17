<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'is_paid',
    'affects_payroll',
    'payroll_deduction_component_code',
    'payroll_encashment_component_code',
    'allow_leave_claim',
    'allow_cash_claim',
    'payroll_toil_payout_component_code',
    'status',
])]
final class LeaveType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'affects_payroll' => 'boolean',
            'allow_leave_claim' => 'boolean',
            'allow_cash_claim' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function encashments(): HasMany
    {
        return $this->hasMany(LeaveEncashment::class);
    }
}
