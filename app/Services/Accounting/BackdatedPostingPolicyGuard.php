<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\BackdatedPostingAssessment;
use App\Enums\BackdatedPostingPolicy;
use App\Models\FinancialSetting;

final class BackdatedPostingPolicyGuard
{
    public function assess(bool $isBackdated, FinancialSetting $settings): BackdatedPostingAssessment
    {
        if (! $isBackdated) {
            return new BackdatedPostingAssessment(isBackdated: false, shouldBlock: false, shouldFlag: false);
        }

        $policy = $settings->backdated_posting_policy ?? BackdatedPostingPolicy::Warn;

        return new BackdatedPostingAssessment(
            isBackdated: true,
            shouldBlock: $policy === BackdatedPostingPolicy::Block,
            shouldFlag: $policy === BackdatedPostingPolicy::Warn,
        );
    }
}
