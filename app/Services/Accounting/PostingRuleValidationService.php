<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\PostingRuleLineData;
use App\Enums\PostingRuleEntrySide;
use App\Models\PostingRuleSet;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Save-time checks for posting rule sets.
 * Amounts resolve at posting time from AmountSource, so debit/credit numeric
 * balance cannot be verified here — only structural side coverage and priority ties.
 */
final class PostingRuleValidationService
{
    /**
     * @param  list<PostingRuleLineData>  $lines
     */
    public function assertHasDebitAndCredit(array $lines): void
    {
        $hasActiveDebit = false;
        $hasActiveCredit = false;

        foreach ($lines as $line) {
            if (($line->status ?? 'active') !== 'active') {
                continue;
            }

            if ($line->entrySide === PostingRuleEntrySide::Debit) {
                $hasActiveDebit = true;
            }

            if ($line->entrySide === PostingRuleEntrySide::Credit) {
                $hasActiveCredit = true;
            }
        }

        if ($hasActiveDebit && $hasActiveCredit) {
            return;
        }

        throw ValidationException::withMessages([
            'lines' => __('At least one active debit line and one active credit line are required.'),
        ]);
    }

    /**
     * Flag same-priority active rule sets whose branch scope and effective dates overlap.
     * Differing priorities for overlapping scope/dates are valid — only ties are warned.
     *
     * @return list<string>
     */
    public function samePriorityOverlapWarnings(
        string $eventType,
        ?int $branchId,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo,
        int $priority,
        ?int $excludeId = null,
    ): array {
        $candidates = PostingRuleSet::query()
            ->where('event_type', $eventType)
            ->where('status', 'active')
            ->where('priority', $priority)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when(
                $branchId === null,
                fn ($q) => $q->whereNull('branch_id'),
                fn ($q) => $q->where('branch_id', $branchId),
            )
            ->get(['id', 'code', 'effective_from', 'effective_to']);

        $conflicts = [];

        foreach ($candidates as $candidate) {
            if (! $this->dateRangesOverlap(
                $effectiveFrom,
                $effectiveTo,
                $candidate->effective_from,
                $candidate->effective_to,
            )) {
                continue;
            }

            $conflicts[] = (string) $candidate->code;
        }

        if ($conflicts === []) {
            return [];
        }

        $codes = implode(', ', $conflicts);

        return [
            __('Another active rule set with the same event type, branch scope, overlapping dates, and priority already exists (:codes). Adjust priority to avoid ambiguous matching.', [
                'codes' => $codes,
            ]),
        ];
    }

    private function dateRangesOverlap(
        Carbon $fromA,
        ?Carbon $toA,
        mixed $fromB,
        mixed $toB,
    ): bool {
        $startB = Carbon::parse($fromB)->startOfDay();
        $endB = $toB !== null ? Carbon::parse($toB)->endOfDay() : null;
        $startA = $fromA->copy()->startOfDay();
        $endA = $toA?->copy()->endOfDay();

        if ($endA !== null && $startB->gt($endA)) {
            return false;
        }

        if ($endB !== null && $startA->gt($endB)) {
            return false;
        }

        return true;
    }
}
