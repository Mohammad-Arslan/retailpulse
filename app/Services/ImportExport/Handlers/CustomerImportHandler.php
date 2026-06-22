<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\CustomerGroup;
use App\Models\LoyaltyTier;
use App\Services\Customer\CustomerService;
use App\Services\Customer\WalletService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class CustomerImportHandler implements ImportHandler
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly WalletService $wallet,
    ) {}

    public function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'phone',
                'label' => 'Phone',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'email'], ['rule' => 'string', 'max' => 255]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'tier',
                'label' => 'Loyalty Tier',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'customer_group',
                'label' => 'Customer Group',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'credit_limit',
                'label' => 'Credit Limit',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'decimal', 'min' => 0]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'opening_wallet_balance',
                'label' => 'Opening Wallet Balance',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'decimal', 'min' => 0]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'is_active',
                'label' => 'Active',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']],
                'default_transforms' => ['cast_bool'],
            ],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $phone = isset($row['phone']) ? (string) $row['phone'] : '';
        $email = isset($row['email']) ? (string) $row['email'] : '';

        if ($phone === '' && $email === '') {
            $errors['phone'] = ['Phone or email is required.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $tierId = null;

            if (! empty($row['tier'])) {
                $tier = LoyaltyTier::query()
                    ->where('slug', (string) $row['tier'])
                    ->orWhere('name', (string) $row['tier'])
                    ->first();
                $tierId = $tier?->id;
            }

            $groupId = null;

            if (! empty($row['customer_group'])) {
                $group = CustomerGroup::query()
                    ->where('slug', (string) $row['customer_group'])
                    ->orWhere('name', (string) $row['customer_group'])
                    ->first();
                $groupId = $group?->id;
            }

            $customer = $this->customers->upsertByPhoneOrEmail([
                'name' => (string) ($row['name'] ?? 'Customer'),
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'is_active' => $row['is_active'] ?? true,
                'loyalty_tier_id' => $tierId,
                'customer_group_id' => $groupId,
                'credit_limit' => isset($row['credit_limit']) && $row['credit_limit'] !== ''
                    ? (float) $row['credit_limit']
                    : null,
            ]);

            if (! empty($row['opening_wallet_balance']) && (float) $row['opening_wallet_balance'] > 0) {
                $this->wallet->topUp(
                    customerId: $customer->id,
                    amount: (float) $row['opening_wallet_balance'],
                    userId: $context->userId ?? 0,
                    meta: ['source' => 'import'],
                    paymentMethod: null,
                );
            }

            return ImportRowResult::success($customer->id);
        });
    }

    public function afterImport(ImportContext $context): void
    {
        //
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
