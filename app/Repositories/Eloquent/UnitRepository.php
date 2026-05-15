<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use Illuminate\Support\Collection;

final class UnitRepository implements UnitRepositoryInterface
{
    public function allActive(): Collection
    {
        return Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation']);
    }
}
