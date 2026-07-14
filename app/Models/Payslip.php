<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_item_id',
    'payslip_number',
    'disk',
    'path',
    'emailed_at',
])]
final class Payslip extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emailed_at' => 'datetime',
        ];
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
