<?php

declare(strict_types=1);

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of Phase 12 expense.recurring_due posting rules for existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $effectiveFrom = '2020-01-01';

        $ruleSet = PostingRuleSet::query()->firstOrCreate(
            ['code' => 'expense_recurring_due_default'],
            [
                'name' => 'Recurring Expense Due — Default',
                'event_type' => 'expense.recurring_due',
                'priority' => 100,
                'effective_from' => $effectiveFrom,
                'status' => 'active',
            ],
        );

        $lines = [
            [1, PostingRuleEntrySide::Debit, AccountResolutionType::ExpenseCategoryAccount, AmountSource::NetAmount, null, true],
            [2, PostingRuleEntrySide::Debit, AccountResolutionType::TaxAccount, AmountSource::TaxAmount, null, false],
            [3, PostingRuleEntrySide::Credit, AccountResolutionType::PaymentMethodAccount, AmountSource::SettlementAmount, null, false],
            [4, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::GrossAmount, 'accounts_payable', false],
        ];

        foreach ($lines as [$sequence, $side, $resolutionType, $amountSource, $mappingKey, $required]) {
            PostingRuleLine::query()->firstOrCreate(
                [
                    'posting_rule_set_id' => $ruleSet->id,
                    'sequence' => $sequence,
                ],
                [
                    'entry_side' => $side,
                    'account_resolution_type' => $resolutionType,
                    'account_mapping_key' => $mappingKey,
                    'warehouse_scope' => null,
                    'amount_source' => $amountSource,
                    'required' => $required,
                    'status' => 'active',
                ],
            );
        }
    }

    public function down(): void
    {
        $ruleSet = PostingRuleSet::query()->where('code', 'expense_recurring_due_default')->first();

        if ($ruleSet === null) {
            return;
        }

        PostingRuleLine::query()->where('posting_rule_set_id', $ruleSet->id)->delete();
        $ruleSet->delete();
    }
};
