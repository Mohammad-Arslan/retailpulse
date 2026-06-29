<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyScopeMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'name',
    'description',
    'scope_type',
    'earn_scope',
    'redeem_scope',
    'allow_cross_branch_earn',
    'allow_cross_branch_redeem',
    'status',
    'starts_at',
    'ends_at',
    'created_by',
    'updated_by',
])]
class LoyaltyProgram extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'scope_type' => LoyaltyProgramScopeType::class,
            'earn_scope' => LoyaltyScopeMode::class,
            'redeem_scope' => LoyaltyScopeMode::class,
            'allow_cross_branch_earn' => 'boolean',
            'allow_cross_branch_redeem' => 'boolean',
            'status' => LoyaltyProgramStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'loyalty_program_branches', 'program_id', 'branch_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'program_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(LoyaltyProgramTier::class, 'program_id');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyWallet::class, 'program_id');
    }

    public function approvalPolicies(): HasMany
    {
        return $this->hasMany(LoyaltyApprovalPolicy::class, 'program_id');
    }

    public function expiryRules(): HasMany
    {
        return $this->hasMany(LoyaltyExpiryRule::class, 'program_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(LoyaltyCampaign::class, 'program_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActiveNow(): bool
    {
        if ($this->status !== LoyaltyProgramStatus::Active) {
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

    public function participatesInBranch(int $branchId): bool
    {
        if ($this->scope_type === LoyaltyProgramScopeType::Global) {
            return true;
        }

        if ($this->scope_type === LoyaltyProgramScopeType::Branch) {
            return $this->branches()->where('branches.id', $branchId)->exists();
        }

        return $this->branches()->where('branches.id', $branchId)->exists();
    }
}
