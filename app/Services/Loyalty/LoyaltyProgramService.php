<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyProgramData;
use App\DTOs\Loyalty\UpdateLoyaltyProgramData;
use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyScopeMode;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyProgramService
{
    public function create(CreateLoyaltyProgramData $data, int $userId): LoyaltyProgram
    {
        return DB::transaction(function () use ($data, $userId) {
            $program = LoyaltyProgram::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'scope_type' => $data->scopeType,
                'earn_scope' => $data->earnScope,
                'redeem_scope' => $data->redeemScope,
                'allow_cross_branch_earn' => $data->allowCrossBranchEarn,
                'allow_cross_branch_redeem' => $data->allowCrossBranchRedeem,
                'status' => LoyaltyProgramStatus::Draft,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($data->branchIds !== null && $data->branchIds !== []) {
                $program->branches()->sync($data->branchIds);
            }

            return $program->load('branches');
        });
    }

    public function update(LoyaltyProgram $program, UpdateLoyaltyProgramData $data, int $userId): LoyaltyProgram
    {
        return DB::transaction(function () use ($program, $data, $userId) {
            $program->update([
                'name' => $data->name,
                'description' => $data->description,
                'scope_type' => $data->scopeType,
                'earn_scope' => $data->earnScope,
                'redeem_scope' => $data->redeemScope,
                'allow_cross_branch_earn' => $data->allowCrossBranchEarn,
                'allow_cross_branch_redeem' => $data->allowCrossBranchRedeem,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'updated_by' => $userId,
            ]);

            if ($data->branchIds !== null) {
                $program->branches()->sync($data->branchIds);
            }

            return $program->fresh(['branches']);
        });
    }

    public function activate(LoyaltyProgram $program, int $userId): LoyaltyProgram
    {
        $program->update([
            'status' => LoyaltyProgramStatus::Active,
            'updated_by' => $userId,
        ]);

        return $program;
    }

    public function deactivate(LoyaltyProgram $program, int $userId): LoyaltyProgram
    {
        $program->update([
            'status' => LoyaltyProgramStatus::Inactive,
            'updated_by' => $userId,
        ]);

        return $program;
    }

    public function resolveActiveProgramForBranch(int $branchId, ?int $tenantId = null): ?LoyaltyProgram
    {
        $query = LoyaltyProgram::query()
            ->where('status', LoyaltyProgramStatus::Active)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->with('branches');

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        }

        /** @var Collection<int, LoyaltyProgram> $programs */
        $programs = $query->orderByDesc('id')->get();

        return $programs->first(fn (LoyaltyProgram $program) => $program->participatesInBranch($branchId));
    }

    public function assertBranchCanEarn(LoyaltyProgram $program, int $branchId, ?int $walletBranchId): void
    {
        if (! $program->participatesInBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch' => __('This branch does not participate in the loyalty program.'),
            ]);
        }

        if ($program->earn_scope === LoyaltyScopeMode::Branch && $walletBranchId !== null && $walletBranchId !== $branchId) {
            if (! $program->allow_cross_branch_earn) {
                throw ValidationException::withMessages([
                    'branch' => __('Cross-branch earning is not allowed for this program.'),
                ]);
            }
        }
    }

    public function assertBranchCanRedeem(LoyaltyProgram $program, int $branchId, ?int $walletBranchId): void
    {
        if (! $program->participatesInBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch' => __('This branch does not participate in the loyalty program.'),
            ]);
        }

        if ($program->redeem_scope === LoyaltyScopeMode::Branch && $walletBranchId !== null && $walletBranchId !== $branchId) {
            if (! $program->allow_cross_branch_redeem) {
                throw ValidationException::withMessages([
                    'branch' => __('Cross-branch redemption is not allowed for this program.'),
                ]);
            }
        }
    }
}
