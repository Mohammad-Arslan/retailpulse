<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'shift_label',
    'start_time',
    'end_time',
    'rest_days',
    'weekend_days_enabled',
    'weekend_days',
    'notes',
])]
class EmployeeShiftPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rest_days' => 'array',
            'weekend_days_enabled' => 'boolean',
            'weekend_days' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
