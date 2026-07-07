<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use Illuminate\Support\Collection;

interface PettyCashRegisterRepositoryInterface
{
    /**
     * @return Collection<int, PettyCashRegister>
     */
    public function allWithRelations(): Collection;

    /**
     * @return Collection<int, PettyCashVoucher>
     */
    public function recentVouchers(int $limit = 25): Collection;

    public function create(array $attributes): PettyCashRegister;
}
