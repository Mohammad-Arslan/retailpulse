<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'posting_rule_set_id',
    'sequence',
    'entry_side',
    'account_resolution_type',
    'account_id',
    'account_mapping_key',
    'amount_source',
    'narration_template',
    'required',
    'status',
])]
class PostingRuleLine extends Model
{
    protected function casts(): array
    {
        return [
            'entry_side' => PostingRuleEntrySide::class,
            'account_resolution_type' => AccountResolutionType::class,
            'amount_source' => AmountSource::class,
            'required' => 'boolean',
        ];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(PostingRuleSet::class, 'posting_rule_set_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
