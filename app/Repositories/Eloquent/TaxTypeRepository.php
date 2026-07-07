<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\TaxType;
use App\Repositories\Contracts\TaxTypeRepositoryInterface;
use Illuminate\Support\Collection;

final class TaxTypeRepository implements TaxTypeRepositoryInterface
{
    public function allOrdered(): Collection
    {
        return TaxType::query()->orderBy('code')->get();
    }

    public function create(array $attributes): TaxType
    {
        return TaxType::query()->create($attributes);
    }
}
