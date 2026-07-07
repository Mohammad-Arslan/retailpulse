<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use App\Repositories\Contracts\PettyCashRegisterRepositoryInterface;
use Illuminate\Support\Collection;

final class PettyCashRegisterRepository implements PettyCashRegisterRepositoryInterface
{
    public function allWithRelations(): Collection
    {
        return PettyCashRegister::query()
            ->with(['branch:id,name', 'coaAccount:id,code,name'])
            ->orderBy('name')
            ->get();
    }

    public function recentVouchers(int $limit = 25): Collection
    {
        return PettyCashVoucher::query()
            ->with('register:id,name')
            ->orderByDesc('date')
            ->limit($limit)
            ->get();
    }

    public function create(array $attributes): PettyCashRegister
    {
        return PettyCashRegister::query()->create($attributes);
    }
}
