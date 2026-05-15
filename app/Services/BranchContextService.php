<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BranchContextService
{
    private const SESSION_KEY = 'branch_id';

    public function resolve(Request $request): BranchContext
    {
        $user = $request->user();

        if ($user === null) {
            return new BranchContext(null, null);
        }

        $accessibleIds = $this->accessibleBranchIds($user);
        $branchId = $this->resolveSessionBranchId($request, $user, $accessibleIds);

        return new BranchContext($branchId, $accessibleIds);
    }

    /**
     * @return list<array{id: int, name: string, code: string, is_primary: bool}>
     */
    public function switcherOptions(User $user): array
    {
        return $this->accessibleBranches($user)
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_primary' => (bool) ($branch->pivot->is_primary ?? false),
            ])
            ->values()
            ->all();
    }

    public function activeBranchPayload(BranchContext $context): ?array
    {
        if ($context->branchId === null) {
            return null;
        }

        $branch = Branch::query()->find($context->branchId);

        if ($branch === null) {
            return null;
        }

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'currency' => $branch->currency,
            'timezone' => $branch->timezone,
        ];
    }

    public function switchBranch(Request $request, User $user, ?int $branchId): void
    {
        $accessibleIds = $this->accessibleBranchIds($user);

        if ($branchId === null) {
            if ($accessibleIds !== null) {
                throw ValidationException::withMessages([
                    'branch_id' => __('You must select a branch.'),
                ]);
            }

            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        if (! $this->canAccessBranchId($branchId, $accessibleIds)) {
            throw ValidationException::withMessages([
                'branch_id' => __('You do not have access to this branch.'),
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $branchId);
    }

    public function initializeSession(Request $request, User $user): void
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $accessibleIds = $this->accessibleBranchIds($user);

        if ($accessibleIds === null) {
            return;
        }

        if ($accessibleIds === []) {
            return;
        }

        $primary = $user->primaryBranch();

        $request->session()->put(
            self::SESSION_KEY,
            $primary?->id ?? $accessibleIds[0],
        );
    }

    /**
     * @return list<int>|null null = unrestricted (all branches)
     */
    public function accessibleBranchIds(User $user): ?array
    {
        if ($user->hasRole('super-admin')) {
            return null;
        }

        if (! $user->hasBranchRestrictions()) {
            return null;
        }

        return $user->branches()->pluck('branches.id')->all();
    }

    /**
     * @param  list<int>|null  $accessibleIds
     */
    private function resolveSessionBranchId(Request $request, User $user, ?array $accessibleIds): ?int
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);

        if ($sessionId !== null) {
            $branchId = (int) $sessionId;

            if ($this->canAccessBranchId($branchId, $accessibleIds)) {
                return $branchId;
            }

            $request->session()->forget(self::SESSION_KEY);
        }

        if ($accessibleIds === null) {
            return null;
        }

        if ($accessibleIds === []) {
            return null;
        }

        $primary = $user->primaryBranch();

        return $primary?->id ?? $accessibleIds[0];
    }

    /**
     * @param  list<int>|null  $accessibleIds
     */
    private function canAccessBranchId(int $branchId, ?array $accessibleIds): bool
    {
        if ($accessibleIds === null) {
            return Branch::query()->whereKey($branchId)->exists();
        }

        return in_array($branchId, $accessibleIds, true);
    }

    private function accessibleBranches(User $user): Collection
    {
        if ($user->hasBranchRestrictions()) {
            return $user->branches()->orderBy('name')->get();
        }

        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
