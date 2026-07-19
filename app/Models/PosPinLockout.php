<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'user_id',
    'failed_attempts',
    'locked_until',
])]
class PosPinLockout extends Model
{
    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function minutesRemaining(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return (int) ceil(now()->diffInMinutes($this->locked_until));
    }
}
