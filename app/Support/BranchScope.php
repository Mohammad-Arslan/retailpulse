<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable query/authorization scoping for branch-owned data, built on the same
 * contract as {@see BranchContext}/{@see BranchContextService::accessibleBranchIds()}:
 * an active branch id narrows to one branch, a non-null accessible-branch list
 * narrows to that set, and null means unrestricted (no filter).
 *
 * This formalizes the ad-hoc `when($context->branchId...)` chains already used in
 * SaleController and AbstractSearchProvider::scopeBranch() so new modules (HR,
 * Leave, Attendance, ...) can adopt the same behavior without re-deriving it.
 */
final class BranchScope
{
    /**
     * Scope a query to the active branch, or to the accessible branch set when
     * no single branch is active. No-op when the context is fully unrestricted.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, BranchContext $context, string $column = 'branch_id'): Builder
    {
        return self::applyIds($query, $context->branchId, $context->accessibleBranchIds, $column);
    }

    /**
     * Same as {@see self::apply()} but for callers that only have a resolved
     * accessible-branch list, not an ambient BranchContext — e.g. queued
     * import/export jobs, which run outside the HTTP request/middleware pipeline
     * where BranchContext is bound.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<TModel>
     */
    public static function applyAccessible(Builder $query, ?array $accessibleBranchIds, string $column = 'branch_id'): Builder
    {
        return self::applyIds($query, null, $accessibleBranchIds, $column);
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    public static function canAccess(int $branchId, ?array $accessibleBranchIds): bool
    {
        if ($accessibleBranchIds === null) {
            return true;
        }

        return in_array($branchId, $accessibleBranchIds, true);
    }

    /**
     * Scope a query whose branch ownership is indirect — via a belongsTo
     * Employee relation's primary_branch_id — rather than an own branch_id
     * column. Used by Leave/Overtime/TOIL models that key on employee_id only.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<TModel>
     */
    public static function applyViaEmployee(Builder $query, ?array $accessibleBranchIds, string $relation = 'employee'): Builder
    {
        if ($accessibleBranchIds === null) {
            return $query;
        }

        return $query->whereHas($relation, fn ($q) => $q->whereIn('primary_branch_id', $accessibleBranchIds));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<TModel>
     */
    private static function applyIds(Builder $query, ?int $branchId, ?array $accessibleBranchIds, string $column): Builder
    {
        if ($branchId !== null) {
            return $query->where($column, $branchId);
        }

        if ($accessibleBranchIds !== null) {
            return $query->whereIn($column, $accessibleBranchIds);
        }

        return $query;
    }
}
