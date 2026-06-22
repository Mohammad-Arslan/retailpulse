<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\ProcurementAlert;
use App\Models\User;
use Illuminate\Support\Collection;

final class ProcurementAlertService
{
    /**
     * @param  array<string, mixed>  $linkParams
     */
    public function notifyUsersWithPermission(
        string $permission,
        ?int $branchId,
        string $type,
        string $dedupeKey,
        string $title,
        string $message,
        ?string $linkRoute = null,
        array $linkParams = [],
    ): void {
        $this->eligibleUsers($permission, $branchId)->each(function (User $user) use (
            $branchId,
            $type,
            $dedupeKey,
            $title,
            $message,
            $linkRoute,
            $linkParams,
        ): void {
            ProcurementAlert::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey.'|user:'.$user->id],
                [
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'link_route' => $linkRoute,
                    'link_params' => $linkParams ?: null,
                ],
            );
        });
    }

    /**
     * @return Collection<int, ProcurementAlert>
     */
    public function recentUnreadForUser(User $user, int $limit = 5): Collection
    {
        return ProcurementAlert::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleUsers(string $permission, ?int $branchId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (User $user) use ($permission, $branchId): bool {
                if (! $user->can($permission)) {
                    return false;
                }

                if ($branchId === null) {
                    return true;
                }

                if (! $user->hasBranchRestrictions()) {
                    return true;
                }

                return $user->branches()->where('branches.id', $branchId)->exists();
            })
            ->values();
    }
}
