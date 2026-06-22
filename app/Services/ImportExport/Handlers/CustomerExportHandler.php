<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Customer;
use App\Services\Customer\CustomerCreditService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\StoreCreditService;
use App\Services\Customer\WalletService;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class CustomerExportHandler implements ExportHandler
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly StoreCreditService $storeCredit,
        private readonly CustomerCreditService $credit,
    ) {}

    public function columns(): array
    {
        return (new CustomerImportHandler(
            app(CustomerService::class),
            $this->wallet,
        ))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = Customer::query()
            ->with(['loyaltyTier', 'customerGroup'])
            ->orderBy('name');

        $filters = $context->options['filters'] ?? [];

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['loyalty_tier_id'])) {
            $query->where('loyalty_tier_id', (int) $filters['loyalty_tier_id']);
        }

        if (! empty($filters['customer_group_id'])) {
            $query->where('customer_group_id', (int) $filters['customer_group_id']);
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Customer $record */
        return [
            'name' => $record->name,
            'phone' => $record->phone ?? '',
            'email' => $record->email ?? '',
            'tier' => $record->loyaltyTier?->slug ?? '',
            'customer_group' => $record->customerGroup?->slug ?? '',
            'credit_limit' => $record->credit_limit !== null
                ? number_format((float) $record->credit_limit, 2, '.', '')
                : '',
            'opening_wallet_balance' => number_format($this->wallet->getAvailableBalance($record->id), 2, '.', ''),
            'is_active' => $record->is_active ? 1 : 0,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
