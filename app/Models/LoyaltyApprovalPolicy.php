<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'program_id',
    'action_type',
    'threshold_type',
    'threshold_value',
    'approval_mode',
    'approver_role_id',
    'is_active',
])]
class LoyaltyApprovalPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'action_type' => LoyaltyApprovalActionType::class,
            'threshold_type' => LoyaltyApprovalThresholdType::class,
            'threshold_value' => 'decimal:2',
            'approval_mode' => LoyaltyApprovalMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }
}
