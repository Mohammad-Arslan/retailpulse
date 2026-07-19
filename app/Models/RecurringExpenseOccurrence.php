<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'recurring_expense_schedule_id',
    'period_key',
    'scheduled_for',
    'amount',
    'functional_amount',
    'status',
    'expense_id',
    'accounting_event_id',
])]
final class RecurringExpenseOccurrence extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'amount' => 'decimal:4',
            'functional_amount' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringExpenseSchedule::class, 'recurring_expense_schedule_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function accountingEvent(): BelongsTo
    {
        return $this->belongsTo(AccountingEvent::class);
    }
}
