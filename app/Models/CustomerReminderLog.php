<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'customer_id', 'channel', 'bucket', 'amount_due', 'status', 'error'])]
class CustomerReminderLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'channel' => ReminderChannel::class,
            'amount_due' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
