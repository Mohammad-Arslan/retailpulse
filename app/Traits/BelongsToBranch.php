<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder): void {
            if (! app()->bound(BranchContext::class)) {
                return;
            }

            $context = app(BranchContext::class);

            if ($context->branchId !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.branch_id',
                    $context->branchId,
                );
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
