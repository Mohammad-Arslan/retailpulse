<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\ArLedgerEntryType;
use App\Models\ArAgingSnapshot;
use App\Models\Branch;
use App\Models\CustomerArLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ArAgingService
{
    public function __construct(
        private readonly CustomerCreditService $credit,
    ) {}

    public function buildSnapshots(?Carbon $date = null): int
    {
        $snapshotDate = ($date ?? now())->toDateString();
        $count = 0;

        Branch::query()->where('is_active', true)->each(function (Branch $branch) use ($snapshotDate, &$count) {
            $customerIds = CustomerArLedger::query()
                ->where('branch_id', $branch->id)
                ->distinct()
                ->pluck('customer_id');

            foreach ($customerIds as $customerId) {
                $outstanding = $this->credit->getOutstandingBalance((int) $customerId, $branch->id);

                if ($outstanding <= 0) {
                    ArAgingSnapshot::query()
                        ->where('snapshot_date', $snapshotDate)
                        ->where('customer_id', $customerId)
                        ->where('branch_id', $branch->id)
                        ->delete();

                    continue;
                }

                $buckets = $this->calculateBuckets((int) $customerId, $branch->id);

                ArAgingSnapshot::query()->updateOrCreate(
                    [
                        'snapshot_date' => $snapshotDate,
                        'customer_id' => $customerId,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'current' => $buckets['current'],
                        'bucket_30' => $buckets['bucket_30'],
                        'bucket_60' => $buckets['bucket_60'],
                        'bucket_90' => $buckets['bucket_90'],
                        'bucket_over_90' => $buckets['bucket_over_90'],
                        'total_outstanding' => $outstanding,
                    ],
                );

                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateReport(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $snapshotDate = (string) ($filters['snapshot_date'] ?? now()->toDateString());

        $query = ArAgingSnapshot::query()
            ->with(['customer.customerGroup', 'branch'])
            ->where('snapshot_date', $snapshotDate)
            ->where('total_outstanding', '>', 0);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['customer_group_id'])) {
            $query->whereHas('customer', fn (Builder $q) => $q->where(
                'customer_group_id',
                (int) $filters['customer_group_id'],
            ));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('customer', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sort = (string) ($filters['sort'] ?? 'total_outstanding');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['total_outstanding', 'current', 'bucket_30', 'bucket_60', 'bucket_90', 'bucket_over_90'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'total_outstanding';
        }

        return $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ArAgingSnapshot>
     */
    public function exportRows(array $filters): Collection
    {
        $snapshotDate = (string) ($filters['snapshot_date'] ?? now()->toDateString());

        $query = ArAgingSnapshot::query()
            ->with(['customer.customerGroup', 'branch'])
            ->where('snapshot_date', $snapshotDate)
            ->where('total_outstanding', '>', 0);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['customer_group_id'])) {
            $query->whereHas('customer', fn (Builder $q) => $q->where(
                'customer_group_id',
                (int) $filters['customer_group_id'],
            ));
        }

        return $query->orderByDesc('total_outstanding')->get();
    }

    /**
     * @return array{current: float, bucket_30: float, bucket_60: float, bucket_90: float, bucket_over_90: float}
     */
    private function calculateBuckets(int $customerId, int $branchId): array
    {
        $buckets = [
            'current' => 0.0,
            'bucket_30' => 0.0,
            'bucket_60' => 0.0,
            'bucket_90' => 0.0,
            'bucket_over_90' => 0.0,
        ];

        $invoices = CustomerArLedger::query()
            ->where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->where('entry_type', ArLedgerEntryType::Invoice)
            ->orderBy('created_at')
            ->get();

        $credits = CustomerArLedger::query()
            ->where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->whereIn('entry_type', [ArLedgerEntryType::Payment, ArLedgerEntryType::WriteOff, ArLedgerEntryType::CreditNote])
            ->sum('amount');

        $remainingCredit = (float) $credits;
        $today = now();

        foreach ($invoices as $invoice) {
            $open = max(0, (float) $invoice->amount - min((float) $invoice->amount, $remainingCredit));
            $remainingCredit = max(0, $remainingCredit - (float) $invoice->amount);

            if ($open <= 0) {
                continue;
            }

            $days = $invoice->created_at !== null
                ? $invoice->created_at->diffInDays($today)
                : 0;

            $key = match (true) {
                $days <= 30 => 'current',
                $days <= 60 => 'bucket_30',
                $days <= 90 => 'bucket_60',
                $days <= 120 => 'bucket_90',
                default => 'bucket_over_90',
            };

            $buckets[$key] += $open;
        }

        return array_map(fn (float $value) => round($value, 2), $buckets);
    }
}
