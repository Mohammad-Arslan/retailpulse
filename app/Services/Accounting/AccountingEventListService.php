<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingEventStatus;
use App\Models\AccountingEvent;
use App\Repositories\Contracts\AccountingEventRepositoryInterface;
use App\Support\AccountingEventPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AccountingEventListService
{
    public function __construct(
        private readonly AccountingEventRepositoryInterface $accountingEventRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, filters: array<string, mixed>}
     */
    public function paginateIndex(array $filters, int $perPage): array
    {
        $status = $filters['status'] ?? AccountingEventStatus::Failed->value;
        $filters['status'] = $status;

        $paginator = $this->accountingEventRepository
            ->paginate($filters, $perPage)
            ->through(fn (AccountingEvent $event) => AccountingEventPresenter::forList($event));

        return ['paginator' => $paginator, 'filters' => $filters];
    }
}
