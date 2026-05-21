<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

final class TenantImportScope
{
    /**
     * Normalize tenant id from user/job records. Legacy imports stored (int) null as 0.
     */
    public static function normalize(int|string|null $tenantId): ?int
    {
        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        $normalized = (int) $tenantId;

        return $normalized === 0 ? null : $normalized;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrain(Builder $query, ?int $tenantId, string $column = 'tenant_id'): Builder
    {
        if ($tenantId === null) {
            return $query->where(function (Builder $scoped) use ($column): void {
                $scoped->whereNull($column)->orWhere($column, 0);
            });
        }

        return $query->where($column, $tenantId);
    }

    public static function constrainExists(Exists $rule, ?int $tenantId, string $column = 'tenant_id'): Exists
    {
        if ($tenantId === null) {
            return $rule->where(function ($query) use ($column): void {
                $query->whereNull($column)->orWhere($column, 0);
            });
        }

        return $rule->where($column, $tenantId);
    }

    public static function constrainUnique(Unique $rule, ?int $tenantId, string $column = 'tenant_id'): Unique
    {
        if ($tenantId === null) {
            return $rule->where(function ($query) use ($column): void {
                $query->whereNull($column)->orWhere($column, 0);
            });
        }

        return $rule->where($column, $tenantId);
    }

    public static function cacheKeySuffix(?int $tenantId): string
    {
        return $tenantId === null ? 'global' : (string) $tenantId;
    }

    /** Persist nullable tenant on legacy NOT NULL columns (0 = global). */
    public static function persist(int|string|null $tenantId): int
    {
        return self::normalize($tenantId) ?? 0;
    }
}
