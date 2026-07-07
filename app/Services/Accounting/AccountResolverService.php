<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

final class AccountResolverService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveByMappingKey(string $mappingKey, array $context = []): ?ChartOfAccount
    {
        $date = isset($context['date']) ? Carbon::parse($context['date']) : now();

        $query = AccountMapping::query()
            ->where('mapping_key', $mappingKey)
            ->where('status', 'active')
            ->with('account')
            ->orderBy('priority');

        if (isset($context['branch_id'])) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('branch_id')->orWhere('branch_id', $context['branch_id']);
            });
        }

        if (isset($context['warehouse_id'])) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('warehouse_id')->orWhere('warehouse_id', $context['warehouse_id']);
            });
        }

        if (isset($context['payment_method'])) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('payment_method')->orWhere('payment_method', $context['payment_method']);
            });
        }

        if (isset($context['currency_code'])) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('currency_code')->orWhere('currency_code', $context['currency_code']);
            });
        }

        $mappings = $query->get();

        $best = $mappings
            ->filter(function (AccountMapping $mapping) use ($date) {
                if ($mapping->effective_from && $date->lt($mapping->effective_from)) {
                    return false;
                }
                if ($mapping->effective_to && $date->gt($mapping->effective_to)) {
                    return false;
                }

                return $mapping->account?->is_postable && $mapping->account?->status === 'active';
            })
            ->sortByDesc(fn (AccountMapping $m) => $this->specificityScore($m))
            ->first();

        return $best?->account;
    }

    private function specificityScore(AccountMapping $mapping): int
    {
        $score = 1000 - $mapping->priority;

        if ($mapping->branch_id) {
            $score += 100;
        }
        if ($mapping->warehouse_id) {
            $score += 50;
        }
        if ($mapping->payment_method) {
            $score += 40;
        }
        if ($mapping->product_category_id) {
            $score += 30;
        }
        if ($mapping->currency_code) {
            $score += 20;
        }

        return $score;
    }
}
