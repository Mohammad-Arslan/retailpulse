<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractSearchProvider implements SearchProvider
{
    public function isAvailable(User $user, BranchContext $context): bool
    {
        $permissions = $this->permissions();

        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function like(string $query): string
    {
        return '%'.addcslashes($query, '%_\\').'%';
    }

    protected function looksLikeCode(string $query): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/.#]*$/', $query);
    }

    /**
     * Scope a query to the active branch or the user's accessible branches.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function scopeBranch($builder, BranchContext $context, string $column = 'branch_id')
    {
        if ($context->branchId !== null) {
            return $builder->where($column, $context->branchId);
        }

        if ($context->accessibleBranchIds !== null) {
            return $builder->whereIn($column, $context->accessibleBranchIds);
        }

        return $builder;
    }
}
