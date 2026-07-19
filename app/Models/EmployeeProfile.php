<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
    'emergency_contact_name',
    'emergency_contact_phone',
    'emergency_contact_relation',
    'attendance_grace_minutes',
    'overtime_eligible',
    'attendance_prefs',
])]
class EmployeeProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_grace_minutes' => 'integer',
            'overtime_eligible' => 'boolean',
            'attendance_prefs' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
