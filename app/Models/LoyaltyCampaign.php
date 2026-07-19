<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyCampaignStatus;
use App\Enums\LoyaltyCampaignType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'program_id',
    'name',
    'description',
    'campaign_type',
    'configuration_json',
    'starts_at',
    'ends_at',
    'status',
])]
class LoyaltyCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'campaign_type' => LoyaltyCampaignType::class,
            'configuration_json' => 'array',
            'status' => LoyaltyCampaignStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function isActiveNow(): bool
    {
        if ($this->status !== LoyaltyCampaignStatus::Active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isBefore($now)) {
            return false;
        }

        return true;
    }
}
