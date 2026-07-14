<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\Expense;
use App\Models\ExpenseApprovalPolicy;
use Illuminate\Support\Collection;

/**
 * Resolves the most specific active expense approval policy
 * (mirrors AccountResolverService specificity scoring).
 */
final class ExpenseApprovalPolicyResolver
{
    public function requiresApproval(Expense $expense): bool
    {
        return $this->resolve($expense) !== null;
    }

    public function resolve(Expense $expense): ?ExpenseApprovalPolicy
    {
        $amount = (string) $expense->amount;
        $date = $expense->expense_date?->toDateString() ?? now()->toDateString();

        /** @var Collection<int, ExpenseApprovalPolicy> $policies */
        $policies = ExpenseApprovalPolicy::query()
            ->where('status', 'active')
            ->where(function ($q) use ($expense): void {
                $q->whereNull('branch_id')->orWhere('branch_id', $expense->branch_id);
            })
            ->where(function ($q) use ($expense): void {
                $q->whereNull('legal_entity_id')->orWhere('legal_entity_id', $expense->legal_entity_id);
            })
            ->where(function ($q) use ($expense): void {
                $q->whereNull('expense_category_id')->orWhere('expense_category_id', $expense->expense_category_id);
            })
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->get()
            ->filter(fn (ExpenseApprovalPolicy $p): bool => bccomp($amount, (string) $p->min_amount, 4) >= 0);

        if ($policies->isEmpty()) {
            return null;
        }

        return $policies
            ->sortByDesc(fn (ExpenseApprovalPolicy $p): int => $this->specificityScore($p))
            ->first();
    }

    private function specificityScore(ExpenseApprovalPolicy $policy): int
    {
        $score = 1000 - (int) $policy->priority;

        if ($policy->legal_entity_id !== null) {
            $score += 150;
        }
        if ($policy->branch_id !== null) {
            $score += 100;
        }
        if ($policy->expense_category_id !== null) {
            $score += 50;
        }

        return $score;
    }
}
