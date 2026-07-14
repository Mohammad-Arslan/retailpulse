<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Models\PostingRuleSet;

final readonly class DuplicatePostingRuleSetResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public PostingRuleSet $ruleSet,
        public array $warnings = [],
    ) {}
}
