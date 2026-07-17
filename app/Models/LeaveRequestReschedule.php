<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_request_id',
    'old_start_date',
    'old_end_date',
    'new_start_date',
    'new_end_date',
    'changed_by',
    'reason',
])]
final class LeaveRequestReschedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_start_date' => 'date',
            'old_end_date' => 'date',
            'new_start_date' => 'date',
            'new_end_date' => 'date',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
