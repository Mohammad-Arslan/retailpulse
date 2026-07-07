<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use Illuminate\Database\Seeder;

final class PostingRulesSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = '2020-01-01';

        $this->seedSaleCompletedRule($effectiveFrom);
        $this->seedPurchaseInvoicePostedRule($effectiveFrom);
        $this->seedPurchaseReceivedRule($effectiveFrom);
        $this->seedPaymentMadeRule($effectiveFrom);
        $this->seedCreditNoteIssuedRule($effectiveFrom);
        $this->seedArWriteOffRule($effectiveFrom);
        $this->seedDebitNoteIssuedRule($effectiveFrom);
        $this->seedPurchaseReturnedRule($effectiveFrom);
        $this->seedSaleReturnedRule($effectiveFrom);
        $this->seedInventoryAdjustedRule($effectiveFrom);
        $this->seedStockScrappedRule($effectiveFrom);
        $this->seedTransferConfirmedRule($effectiveFrom);
    }

    private function seedSaleCompletedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'sale_completed_default',
            name: 'Sale Completed — Default',
            eventType: 'sale.completed',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::PaymentMethodAccount, AmountSource::SettlementAmount);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::NetAmount, 'sales_revenue');
        $this->createLine($ruleSet->id, 3, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::TaxAmount, 'output_tax', required: false);
        $this->createLine($ruleSet->id, 4, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'cogs', required: false);
        $this->createLine($ruleSet->id, 5, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
    }

    private function seedPurchaseInvoicePostedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'purchase_invoice_posted_default',
            name: 'Purchase Invoice Posted — Default',
            eventType: 'purchase.invoice_posted',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::NetAmount, 'inventory_asset', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::TaxAmount, 'input_tax', required: false);
        $this->createLine($ruleSet->id, 3, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::GrossAmount, 'accounts_payable');
    }

    private function seedPurchaseReceivedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'purchase_received_default',
            name: 'Purchase Received — Default',
            eventType: 'purchase.received',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::LandedCost, 'inventory_asset');
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::LandedCost, 'accounts_payable');
    }

    private function seedPaymentMadeRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'payment_made_default',
            name: 'Payment Made — Default',
            eventType: 'payment.made',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'accounts_payable');
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::PaymentMethodAccount, AmountSource::SettlementAmount);
    }

    private function seedCreditNoteIssuedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'credit_note_issued_default',
            name: 'Credit Note Issued — Default',
            eventType: 'credit_note.issued',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'sales_return', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::TaxAmount, 'output_tax', required: false);
        $this->createLine($ruleSet->id, 3, PostingRuleEntrySide::Credit, AccountResolutionType::CustomerReceivableAccount, AmountSource::GrossAmount);
    }

    private function seedArWriteOffRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'ar_write_off_default',
            name: 'AR Write-Off — Default',
            eventType: 'ar.write_off',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'bad_debt_expense', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::CustomerReceivableAccount, AmountSource::SettlementAmount);
    }

    private function seedDebitNoteIssuedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'debit_note_issued_default',
            name: 'Debit Note Issued — Default',
            eventType: 'debit_note.issued',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'accounts_payable');
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'inventory_asset', required: false);
    }

    private function seedPurchaseReturnedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'purchase_returned_default',
            name: 'Purchase Returned — Default',
            eventType: 'purchase.returned',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'accounts_payable');
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
    }

    private function seedSaleReturnedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'sale_returned_default',
            name: 'Sale Returned — Default',
            eventType: 'sale.returned',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'cogs', required: false);
    }

    private function seedInventoryAdjustedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'inventory_adjusted_default',
            name: 'Inventory Adjusted — Default',
            eventType: 'inventory.adjusted',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_adjustment', required: false);
    }

    private function seedStockScrappedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'stock_scrapped_default',
            name: 'Stock Scrapped — Default',
            eventType: 'stock.scrapped',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_write_off', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
    }

    private function seedTransferConfirmedRule(string $effectiveFrom): void
    {
        $ruleSet = $this->createRuleSet(
            code: 'transfer_confirmed_default',
            name: 'Transfer Confirmed — Default',
            eventType: 'transfer.confirmed',
            effectiveFrom: $effectiveFrom,
        );

        $this->createLine($ruleSet->id, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
        $this->createLine($ruleSet->id, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'inventory_asset', required: false);
    }

    private function createRuleSet(
        string $code,
        string $name,
        string $eventType,
        string $effectiveFrom,
    ): PostingRuleSet {
        return PostingRuleSet::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'event_type' => $eventType,
                'priority' => 100,
                'effective_from' => $effectiveFrom,
                'status' => 'active',
            ],
        );
    }

    private function createLine(
        int $ruleSetId,
        int $sequence,
        PostingRuleEntrySide $side,
        AccountResolutionType $resolutionType,
        AmountSource $amountSource,
        ?string $mappingKey = null,
        bool $required = true,
    ): void {
        PostingRuleLine::query()->firstOrCreate(
            [
                'posting_rule_set_id' => $ruleSetId,
                'sequence' => $sequence,
            ],
            [
                'entry_side' => $side,
                'account_resolution_type' => $resolutionType,
                'account_mapping_key' => $mappingKey,
                'amount_source' => $amountSource,
                'required' => $required,
                'status' => 'active',
            ],
        );
    }
}
