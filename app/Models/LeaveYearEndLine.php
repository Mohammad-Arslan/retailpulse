<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'leave_year_end_run_id',
    'employee_id',
    'leave_type_id',
    'carried_forward',
    'expired',
    'encashed',
    'next_opening',
])]
final class LeaveYearEndLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carried_forward' => 'decimal:2',
            'expired' => 'decimal:2',
            'encashed' => 'decimal:2',
            'next_opening' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LeaveYearEndRun::class, 'leave_year_end_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
