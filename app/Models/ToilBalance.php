<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'available_hours',
    'pending_hours',
])]
final class ToilBalance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_hours' => 'decimal:2',
            'pending_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return Attribute<float, never>
     */
    protected function totalHours(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->available_hours + (float) $this->pending_hours);
    }
}
