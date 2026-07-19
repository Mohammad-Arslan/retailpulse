<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyRuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'program_id',
    'name',
    'description',
    'rule_type',
    'priority',
    'conditions_json',
    'reward_json',
    'is_active',
    'effective_from',
    'effective_to',
])]
class LoyaltyRule extends Model
{
    protected function casts(): array
    {
        return [
            'rule_type' => LoyaltyRuleType::class,
            'priority' => 'integer',
            'conditions_json' => 'array',
            'reward_json' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function isEffectiveNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from !== null && $this->effective_from->isAfter($now)) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to->isBefore($now)) {
            return false;
        }

        return true;
    }
}
