<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CountSessionStatus;
use App\Models\CountScheduleRule;
use App\Models\CountSession;
use App\Repositories\Contracts\CountSessionRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CreateScheduledCountSessionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CountSessionRepositoryInterface $sessions): void
    {
        CountScheduleRule::query()
            ->where('is_active', true)
            ->with(['warehouse', 'branch'])
            ->get()
            ->each(function (CountScheduleRule $rule) use ($sessions): void {
                if (! $this->isDue($rule)) {
                    return;
                }

                CountSession::query()->create([
                    'reference_no' => $sessions->nextReferenceNo(),
                    'branch_id' => $rule->branch_id,
                    'warehouse_id' => $rule->warehouse_id,
                    'scope_type' => $rule->scope_type,
                    'scope_id' => $rule->scope_id,
                    'status' => CountSessionStatus::Draft,
                    'blind_count' => $rule->blind_count,
                    'freeze_mode' => false,
                    'created_by' => null,
                ]);

                $rule->update(['last_run_at' => now()]);
            });
    }

    private function isDue(CountScheduleRule $rule): bool
    {
        $now = now();

        return match ($rule->frequency) {
            'daily' => $rule->last_run_at === null || $rule->last_run_at->lt($now->copy()->startOfDay()),
            'weekly' => $rule->day_of_week === $now->dayOfWeek
                && ($rule->last_run_at === null || $rule->last_run_at->lt($now->copy()->startOfWeek())),
            'monthly' => $rule->day_of_month === $now->day
                && ($rule->last_run_at === null || $rule->last_run_at->lt($now->copy()->startOfMonth())),
            default => false,
        };
    }
}
