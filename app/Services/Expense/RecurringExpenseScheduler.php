<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\RecurringExpenseOccurrence;
use App\Models\RecurringExpenseSchedule;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CurrencyConversionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RecurringExpenseScheduler
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly CurrencyConversionService $currencyConversion,
    ) {}

    /**
     * @return Collection<int, RecurringExpenseOccurrence>
     */
    public function processDue(?CarbonImmutable $asOf = null): Collection
    {
        $asOf ??= CarbonImmutable::now();
        $processed = collect();

        RecurringExpenseSchedule::query()
            ->with(['category'])
            ->where('status', 'active')
            ->where('start_date', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $asOf->toDateString());
            })
            ->where('next_run_at', '<=', $asOf)
            ->orderBy('id')
            ->each(function (RecurringExpenseSchedule $schedule) use ($asOf, $processed): void {
                $occurrence = $this->processSchedule($schedule, $asOf);

                if ($occurrence !== null) {
                    $processed->push($occurrence);
                }
            });

        return $processed;
    }

    private function processSchedule(
        RecurringExpenseSchedule $schedule,
        CarbonImmutable $asOf,
    ): ?RecurringExpenseOccurrence {
        if ($schedule->end_date !== null && $schedule->next_run_at->toDateString() > $schedule->end_date->toDateString()) {
            return null;
        }

        $runAt = CarbonImmutable::parse($schedule->next_run_at);
        $periodKey = $this->computePeriodKey($schedule, $runAt);
        $scheduledFor = $runAt->toDateString();
        $amount = $this->resolveAmount($schedule, $runAt, $periodKey);

        return DB::transaction(function () use ($schedule, $periodKey, $scheduledFor, $amount, $asOf): RecurringExpenseOccurrence {
            $occurrence = RecurringExpenseOccurrence::query()->firstOrCreate(
                [
                    'recurring_expense_schedule_id' => $schedule->id,
                    'period_key' => $periodKey,
                ],
                [
                    'scheduled_for' => $scheduledFor,
                    'amount' => $amount,
                    'status' => 'pending',
                    'created_at' => now(),
                ],
            );

            if ($occurrence->status !== 'posted') {
                $this->publishOccurrence($schedule, $occurrence);
            }

            if ($schedule->next_run_at <= $asOf) {
                $schedule->update([
                    'next_run_at' => $this->computeNextRunAt($schedule, $runAt),
                ]);
            }

            return $occurrence->fresh(['schedule.category', 'accountingEvent']);
        });
    }

    private function publishOccurrence(
        RecurringExpenseSchedule $schedule,
        RecurringExpenseOccurrence $occurrence,
    ): void {
        $schedule->loadMissing('category');

        $net = (string) $occurrence->amount;
        $tax = '0.0000';
        $gross = bcadd($net, $tax, 4);
        $paid = $schedule->payment_method !== null && $schedule->payment_method !== '';

        $fx = $this->currencyConversion->convertToFunctional(
            (float) $net,
            $schedule->currency_code,
            null,
            $occurrence->scheduled_for?->toDateString(),
        );

        $payload = [
            'date' => $occurrence->scheduled_for->toDateString(),
            'branch_id' => $schedule->branch_id,
            'legal_entity_id' => $schedule->legal_entity_id,
            'cost_centre_id' => $schedule->cost_centre_id,
            'currency_code' => $schedule->currency_code,
            'exchange_rate' => $fx['exchange_rate'],
            'net_amount' => (float) $net,
            'tax_amount' => (float) $tax,
            'gross_amount' => $paid ? 0.0 : (float) $gross,
            'settlement_amount' => $paid ? (float) $gross : 0.0,
            'tax_type_id' => $schedule->tax_type_id,
            'tax_direction' => 'purchase',
            'payment_method' => $schedule->payment_method,
            'expense_category_id' => $schedule->expense_category_id,
            'expense_account_mapping_key' => $schedule->category->resolvedAccountMappingKey(),
            'description' => "Recurring Expense {$occurrence->period_key}",
            'source_module' => 'expenses',
            'source_number' => "REC-{$schedule->id}-{$occurrence->period_key}",
            'user_id' => $schedule->created_by ?? 1,
        ];

        if ($paid) {
            $payload['payments'] = [[
                'method' => $schedule->payment_method,
                'amount' => (float) $gross,
                'status' => 'completed',
            ]];
        }

        $existing = $this->accountingEvents->findForSource(
            'expense.recurring_due',
            RecurringExpenseOccurrence::class,
            $occurrence->id,
        );

        if ($existing?->journal_entry_id) {
            $occurrence->update([
                'status' => 'posted',
                'functional_amount' => number_format($fx['functional_amount'], 4, '.', ''),
                'accounting_event_id' => $existing->id,
            ]);

            return;
        }

        $event = $this->accountingEvents->process(
            'expense.recurring_due',
            RecurringExpenseOccurrence::class,
            $occurrence->id,
            $payload,
            (int) ($schedule->created_by ?? 1),
        );

        $occurrence->update([
            'status' => 'posted',
            'functional_amount' => number_format($fx['functional_amount'], 4, '.', ''),
            'accounting_event_id' => $event->id,
        ]);
    }

    private function resolveAmount(
        RecurringExpenseSchedule $schedule,
        CarbonImmutable $runAt,
        string $periodKey,
    ): string {
        $base = (string) $schedule->amount;

        if (! in_array($schedule->proration_policy, ['first_period', 'both'], true)) {
            return $base;
        }

        if (! $this->isFirstPeriod($schedule, $periodKey)) {
            return $base;
        }

        $start = CarbonImmutable::parse($schedule->start_date);

        if ($this->isPeriodStart($schedule, $start)) {
            return $base;
        }

        $periodStart = $this->periodStart($schedule, $runAt);
        $periodEnd = $this->periodEnd($schedule, $runAt);
        $daysInPeriod = (string) ($periodStart->diffInDays($periodEnd) + 1);
        $daysRemaining = (string) ($start->diffInDays($periodEnd) + 1);

        if (bccomp($daysInPeriod, '0', 0) <= 0 || bccomp($daysRemaining, $daysInPeriod, 0) > 0) {
            return $base;
        }

        return bcdiv(bcmul($base, $daysRemaining, 8), $daysInPeriod, 4);
    }

    private function isFirstPeriod(RecurringExpenseSchedule $schedule, string $periodKey): bool
    {
        $start = CarbonImmutable::parse($schedule->start_date);

        return $periodKey === $this->computePeriodKey($schedule, $start);
    }

    private function isPeriodStart(RecurringExpenseSchedule $schedule, CarbonImmutable $date): bool
    {
        return $date->toDateString() === $this->periodStart($schedule, $date)->toDateString();
    }

    private function periodStart(RecurringExpenseSchedule $schedule, CarbonImmutable $date): CarbonImmutable
    {
        return match ($schedule->frequency) {
            'daily' => $date->startOfDay(),
            'weekly' => $date->startOfWeek(),
            'monthly' => $date->startOfMonth(),
            'quarterly' => $date->startOfQuarter(),
            'annual' => $date->startOfYear(),
            default => $date->startOfDay(),
        };
    }

    private function periodEnd(RecurringExpenseSchedule $schedule, CarbonImmutable $date): CarbonImmutable
    {
        return match ($schedule->frequency) {
            'daily' => $date->startOfDay(),
            'weekly' => $date->endOfWeek()->startOfDay(),
            'monthly' => $date->endOfMonth()->startOfDay(),
            'quarterly' => $date->endOfQuarter()->startOfDay(),
            'annual' => $date->endOfYear()->startOfDay(),
            default => $date->startOfDay(),
        };
    }

    public function computePeriodKey(RecurringExpenseSchedule $schedule, CarbonImmutable $date): string
    {
        return match ($schedule->frequency) {
            'daily', 'custom_interval' => $date->format('Y-m-d'),
            'weekly' => $date->format('o').'-W'.str_pad($date->format('W'), 2, '0', STR_PAD_LEFT),
            'monthly' => $date->format('Y-m'),
            'quarterly' => $date->format('Y').'-Q'.(string) (int) ceil($date->month / 3),
            'annual' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    private function computeNextRunAt(
        RecurringExpenseSchedule $schedule,
        CarbonImmutable $currentRun,
    ): CarbonImmutable {
        $interval = max(1, (int) $schedule->interval_count);

        $next = match ($schedule->frequency) {
            'daily' => $currentRun->addDays($interval),
            'weekly' => $currentRun->addWeeks($interval),
            'monthly' => $this->addMonthsPreservingDay($currentRun, $interval, $schedule->day_of_period),
            'quarterly' => $this->addMonthsPreservingDay($currentRun, $interval * 3, $schedule->day_of_period),
            'annual' => $this->addMonthsPreservingDay($currentRun, $interval * 12, $schedule->day_of_period),
            'custom_interval' => $currentRun->addDays($interval),
            default => $currentRun->addMonth(),
        };

        if ($schedule->end_date !== null && $next->toDateString() > $schedule->end_date->toDateString()) {
            return CarbonImmutable::parse($schedule->end_date)->endOfDay();
        }

        return $next->startOfDay();
    }

    private function addMonthsPreservingDay(
        CarbonImmutable $date,
        int $months,
        ?int $dayOfPeriod,
    ): CarbonImmutable {
        $next = $date->addMonths($months);

        if ($dayOfPeriod === null) {
            return $next;
        }

        $daysInMonth = $next->daysInMonth;
        $day = min($dayOfPeriod, $daysInMonth);

        return $next->setDay($day);
    }
}
