<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Enums\PostingRuleWarehouseScope;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use App\Models\TaxType;
use Carbon\Carbon;
use DomainException;

final class PostingRuleEngine
{
    public function __construct(
        private readonly AccountResolverService $resolver,
        private readonly CurrencyConversionService $currencyConversion,
        private readonly TaxLedgerService $taxLedger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function buildJournalLines(string $eventType, array $payload): array
    {
        $ruleSet = $this->findRuleSet($eventType, $payload);

        if ($ruleSet === null) {
            throw new DomainException("No active posting rule set for event: {$eventType}");
        }

        $lines = [];

        foreach ($ruleSet->lines as $line) {
            if ($line->status !== 'active') {
                continue;
            }

            if ($this->shouldExpandPaymentMethodSettlement($line, $payload)) {
                foreach ($this->completedPayments($payload) as $payment) {
                    $paymentPayload = $payload;
                    $paymentPayload['payment_method'] = $payment['method'];
                    $paymentPayload['settlement_amount'] = (float) $payment['amount'];

                    $built = $this->buildSingleLine($line, $paymentPayload);
                    if ($built !== null) {
                        $lines[] = $built;
                    }
                }

                continue;
            }

            $built = $this->buildSingleLine($line, $payload);
            if ($built !== null) {
                $lines[] = $built;
            }
        }

        if ($lines === []) {
            throw new DomainException("Posting rule produced no journal lines for: {$eventType}");
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function buildSingleLine(PostingRuleLine $line, array $payload): ?array
    {
        $amount = $this->resolveAmount($line->amount_source, $payload, $line);

        if ($amount <= 0) {
            if ($line->required) {
                throw new DomainException("Required posting rule line {$line->id} resolved to a non-positive amount.");
            }

            return null;
        }

        $account = $this->resolveAccount($line, $payload);

        if ($account === null) {
            if ($line->required) {
                throw new DomainException("Could not resolve account for rule line {$line->id}");
            }

            return null;
        }

        $isDebit = $line->entry_side === PostingRuleEntrySide::Debit;
        $currencyCode = $payload['currency_code'] ?? $this->currencyConversion->functionalCurrencyCode();
        $fx = $this->currencyConversion->convertToFunctional(
            $amount,
            $currencyCode,
            isset($payload['exchange_rate']) ? (float) $payload['exchange_rate'] : null,
            $payload['date'] ?? now()->toDateString(),
        );
        $functionalAmount = $fx['functional_amount'];
        $taxType = $this->resolveTaxTypeForLine($line, $payload);
        $warehouseId = $this->resolveWarehouseIdForLine($line, $payload);

        return [
            'account_id' => $account->id,
            'debit' => $isDebit ? $functionalAmount : 0,
            'credit' => $isDebit ? 0 : $functionalAmount,
            'functional_currency_amount' => $isDebit ? $functionalAmount : -$functionalAmount,
            'transaction_currency_amount' => $currencyCode !== $this->currencyConversion->functionalCurrencyCode()
                ? $fx['transaction_amount']
                : null,
            'currency_code' => $currencyCode,
            'exchange_rate' => $fx['exchange_rate'],
            'branch_id' => $payload['branch_id'] ?? null,
            'warehouse_id' => $warehouseId,
            'party_type' => $payload['party_type'] ?? null,
            'party_id' => $payload['party_id'] ?? null,
            'tax_type_id' => $taxType?->id,
            'description' => $line->narration_template,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldExpandPaymentMethodSettlement(PostingRuleLine $line, array $payload): bool
    {
        return $line->account_resolution_type === AccountResolutionType::PaymentMethodAccount
            && $line->amount_source === AmountSource::SettlementAmount
            && $this->completedPayments($payload) !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{method: string, amount: float, status?: string}>
     */
    private function completedPayments(array $payload): array
    {
        $payments = $payload['payments'] ?? null;

        if (! is_array($payments) || $payments === []) {
            return [];
        }

        $completed = [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $status = strtolower((string) ($payment['status'] ?? 'completed'));
            if (! in_array($status, ['completed', ''], true)) {
                continue;
            }

            $amount = (float) ($payment['amount'] ?? 0);
            $method = (string) ($payment['method'] ?? '');

            if ($amount <= 0 || $method === '') {
                continue;
            }

            $completed[] = [
                'method' => $method,
                'amount' => $amount,
                'status' => $status,
            ];
        }

        return $completed;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function findRuleSet(string $eventType, array $payload): ?PostingRuleSet
    {
        $date = isset($payload['date']) ? Carbon::parse($payload['date']) : now();

        return PostingRuleSet::query()
            ->where('event_type', $eventType)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->when(isset($payload['branch_id']), fn ($q) => $q->where(function ($q) use ($payload) {
                $q->whereNull('branch_id')->orWhere('branch_id', $payload['branch_id']);
            }))
            ->with('lines')
            ->orderBy('priority')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveWarehouseIdForLine(PostingRuleLine $line, array $payload): ?int
    {
        $scope = $line->warehouse_scope;

        $id = match ($scope) {
            PostingRuleWarehouseScope::Source => $payload['from_warehouse_id'] ?? $payload['warehouse_id'] ?? null,
            PostingRuleWarehouseScope::Destination => $payload['to_warehouse_id'] ?? $payload['warehouse_id'] ?? null,
            default => $payload['warehouse_id'] ?? null,
        };

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAccount(PostingRuleLine $line, array $payload): ?ChartOfAccount
    {
        $context = [
            'branch_id' => $payload['branch_id'] ?? null,
            'warehouse_id' => $this->resolveWarehouseIdForLine($line, $payload),
            'payment_method' => $payload['payment_method'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
            'legal_entity_id' => $payload['legal_entity_id'] ?? null,
            'product_category_id' => $payload['product_category_id'] ?? null,
            'date' => $payload['date'] ?? now()->toDateString(),
        ];

        return match ($line->account_resolution_type) {
            AccountResolutionType::FixedAccount => $line->account,
            AccountResolutionType::AccountMapping,
            AccountResolutionType::ConfigurableMapping => $this->resolver->resolveByMappingKey(
                $line->account_mapping_key ?? '',
                $context,
            ),
            AccountResolutionType::PaymentMethodAccount => $this->resolver->resolveByMappingKey(
                'payment_method_account',
                $context,
            ) ?? $this->resolver->resolveByMappingKey('cash_on_hand', $context),
            AccountResolutionType::CustomerReceivableAccount => $this->resolver->resolveByMappingKey(
                'accounts_receivable',
                $context,
            ),
            AccountResolutionType::SupplierPayableAccount => $this->resolver->resolveByMappingKey(
                'accounts_payable',
                $context,
            ),
            AccountResolutionType::WarehouseInventoryAccount => $this->resolver->resolveByMappingKey(
                'inventory_asset',
                $context,
            ),
            AccountResolutionType::TaxAccount => $this->resolver->resolveByMappingKey(
                ($payload['tax_direction'] ?? 'sales') === 'purchase' ? 'input_tax' : 'output_tax',
                $context,
            ),
            AccountResolutionType::BankAccount => $this->resolveBankAccount($payload, $line),
            AccountResolutionType::ProductCategoryAccount => $this->resolveProductCategoryAccount($line, $context, $payload),
            AccountResolutionType::AssetAccount => $this->resolveAssetAccount($line, $payload),
            AccountResolutionType::ExpenseCategoryAccount => $this->resolver->resolveByMappingKey(
                (string) ($payload['expense_account_mapping_key'] ?? $line->account_mapping_key ?? 'expense_default'),
                $context,
            ),
            default => $line->account,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveBankAccount(array $payload, PostingRuleLine $line): ?ChartOfAccount
    {
        $bankAccountId = $payload['bank_account_id'] ?? null;

        if ($bankAccountId !== null) {
            $bankAccount = BankAccount::query()->with('coaAccount')->find((int) $bankAccountId);

            return $bankAccount?->coaAccount;
        }

        return $this->resolver->resolveByMappingKey('bank_account', [
            'branch_id' => $payload['branch_id'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
            'date' => $payload['date'] ?? now()->toDateString(),
        ]) ?? $line->account;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $payload
     */
    private function resolveProductCategoryAccount(
        PostingRuleLine $line,
        array $context,
        array $payload,
    ): ?ChartOfAccount {
        if (! empty($payload['product_category_id'])) {
            $context['product_category_id'] = $payload['product_category_id'];
        }

        $mappingKey = $line->account_mapping_key ?: 'sales_revenue';

        $mapping = AccountMapping::query()
            ->where('mapping_key', $mappingKey)
            ->where('status', 'active')
            ->when(isset($context['product_category_id']), fn ($q) => $q->where('product_category_id', $context['product_category_id']))
            ->orderBy('priority')
            ->first();

        if ($mapping?->account !== null) {
            return $mapping->account;
        }

        return $this->resolver->resolveByMappingKey($mappingKey, $context) ?? $line->account;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAssetAccount(PostingRuleLine $line, array $payload): ?ChartOfAccount
    {
        $assetId = $payload['fixed_asset_id'] ?? null;

        if ($assetId === null) {
            return null;
        }

        $asset = FixedAsset::query()->with('category')->find($assetId);

        if ($asset === null) {
            return null;
        }

        $column = match ($line->account_mapping_key) {
            'asset_account' => 'asset_account_id',
            'accumulated_depreciation_account' => 'accumulated_depreciation_account_id',
            'depreciation_expense_account' => 'depreciation_expense_account_id',
            default => throw new DomainException(
                "Invalid account_mapping_key '{$line->account_mapping_key}' for asset_account resolution on rule line {$line->id}",
            ),
        };

        $accountId = $asset->{$column} ?? $asset->category?->{$column};

        return $accountId ? ChartOfAccount::find($accountId) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAmount(AmountSource $source, array $payload, PostingRuleLine $line): float
    {
        $amount = match ($source) {
            AmountSource::GrossAmount => (float) ($payload['gross_amount'] ?? 0),
            AmountSource::NetAmount => (float) ($payload['net_amount'] ?? 0),
            AmountSource::TaxAmount => (float) ($payload['tax_amount'] ?? 0),
            AmountSource::DiscountAmount => (float) ($payload['discount_amount'] ?? 0),
            AmountSource::ShippingAmount => (float) ($payload['shipping_amount'] ?? 0),
            AmountSource::InventoryCost => (float) ($payload['inventory_cost'] ?? 0),
            AmountSource::LandedCost => (float) ($payload['landed_cost'] ?? 0),
            AmountSource::SettlementAmount => (float) ($payload['settlement_amount'] ?? $payload['amount'] ?? 0),
            AmountSource::ExchangeDifference => (float) ($payload['exchange_difference'] ?? 0),
            AmountSource::DepreciationAmount => (float) ($payload['depreciation_amount'] ?? 0),
            AmountSource::CustomFormula => (float) ($payload['custom_amount'] ?? 0),
        };

        if ($source !== AmountSource::TaxAmount) {
            return $amount;
        }

        $direction = (string) ($payload['tax_direction'] ?? 'sales');
        $taxType = $this->taxLedger->resolveDefaultTaxType($payload, $direction);

        if ($taxType === null) {
            return $amount;
        }

        if ($direction === 'purchase' && (float) $taxType->recoverable_percentage < 100) {
            return round($amount * ((float) $taxType->recoverable_percentage / 100), 2);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTaxTypeForLine(PostingRuleLine $line, array $payload): ?TaxType
    {
        if ($line->amount_source === AmountSource::TaxAmount || $line->account_resolution_type === AccountResolutionType::TaxAccount) {
            $direction = (string) ($payload['tax_direction'] ?? 'sales');

            return $this->taxLedger->resolveDefaultTaxType($payload, $direction);
        }

        return null;
    }
}
