<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'payroll_run_id',
    'employee_id',
    'gross',
    'total_deductions',
    'total_employer_contributions',
    'net_pay',
    'ytd_json',
    'snapshot_json',
])]
final class PayrollItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'total_employer_contributions' => 'decimal:4',
            'net_pay' => 'decimal:4',
            'ytd_json' => 'array',
            'snapshot_json' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class)->orderBy('sequence');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}
