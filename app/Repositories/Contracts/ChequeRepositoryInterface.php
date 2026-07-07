<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Cheque;
use Illuminate\Support\Collection;

interface ChequeRepositoryInterface
{
    /**
     * @return Collection<int, Cheque>
     */
    public function recent(int $limit = 100): Collection;
}
