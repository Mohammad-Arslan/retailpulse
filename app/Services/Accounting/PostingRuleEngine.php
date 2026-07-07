<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\ChartOfAccount;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use Carbon\Carbon;
use DomainException;

final class PostingRuleEngine
{
    public function __construct(
        private readonly AccountResolverService $resolver,
        private readonly CurrencyConversionService $currencyConversion,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function buildJournalLines(string $eventType, array $payload): array
    {
        $ruleSet = $this->resolveRuleSet($eventType, $payload);

        if ($ruleSet === null) {
            throw new DomainException("No active posting rule set for event: {$eventType}");
        }

        $lines = [];

        foreach ($ruleSet->lines as $line) {
            if ($line->status !== 'active') {
                continue;
            }

            $amount = $this->resolveAmount($line->amount_source, $payload);

            if ($amount <= 0) {
                if ($line->required) {
                    throw new DomainException("Required posting rule line {$line->id} resolved to a non-positive amount.");
                }

                continue;
            }

            $account = $this->resolveAccount($line, $payload);

            if ($account === null) {
                if ($line->required) {
                    throw new DomainException("Could not resolve account for rule line {$line->id}");
                }

                continue;
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

            $lines[] = [
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
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'party_type' => $payload['party_type'] ?? null,
                'party_id' => $payload['party_id'] ?? null,
                'tax_type_id' => $payload['tax_type_id'] ?? null,
                'description' => $line->narration_template,
            ];
        }

        if ($lines === []) {
            throw new DomainException("Posting rule produced no journal lines for: {$eventType}");
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveRuleSet(string $eventType, array $payload): ?PostingRuleSet
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
    private function resolveAccount(PostingRuleLine $line, array $payload): ?ChartOfAccount
    {
        $context = [
            'branch_id' => $payload['branch_id'] ?? null,
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
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
            default => $line->account,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAmount(AmountSource $source, array $payload): float
    {
        return match ($source) {
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
    }
}
