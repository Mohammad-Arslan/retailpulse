<?php

declare(strict_types=1);

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $ruleSet = PostingRuleSet::query()->firstOrCreate(
            ['code' => 'payroll_posted_default'],
            [
                'name' => 'Payroll Posted — Default',
                'event_type' => 'payroll.posted',
                'priority' => 100,
                'effective_from' => '2020-01-01',
                'status' => 'active',
            ],
        );

        $lines = [
            [1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::GrossAmount, 'payroll_expense', true],
            [2, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'employer_contribution_expense', false],
            [3, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::TaxAmount, 'tax_withheld_payable', false],
            [4, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::SettlementAmount, 'net_salary_payable', true],
            [5, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::DiscountAmount, 'statutory_payable', false],
            [6, PostingRuleEntrySide::Credit, AccountResolutionType::AccountMapping, AmountSource::InventoryCost, 'statutory_payable', false],
        ];

        foreach ($lines as [$sequence, $side, $resolution, $amount, $key, $required]) {
            PostingRuleLine::query()->firstOrCreate(
                [
                    'posting_rule_set_id' => $ruleSet->id,
                    'sequence' => $sequence,
                ],
                [
                    'entry_side' => $side,
                    'account_resolution_type' => $resolution,
                    'account_mapping_key' => $key,
                    'amount_source' => $amount,
                    'required' => $required,
                    'status' => 'active',
                ],
            );
        }
    }

    public function down(): void
    {
        $ruleSet = PostingRuleSet::query()->where('code', 'payroll_posted_default')->first();
        if ($ruleSet !== null) {
            PostingRuleLine::query()->where('posting_rule_set_id', $ruleSet->id)->delete();
            $ruleSet->delete();
        }
    }
};
