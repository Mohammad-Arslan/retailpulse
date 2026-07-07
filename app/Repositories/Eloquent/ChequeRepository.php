<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Cheque;
use App\Repositories\Contracts\ChequeRepositoryInterface;
use Illuminate\Support\Collection;

final class ChequeRepository implements ChequeRepositoryInterface
{
    public function recent(int $limit = 100): Collection
    {
        return Cheque::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
