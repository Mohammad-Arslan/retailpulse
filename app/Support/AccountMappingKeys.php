<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical mapping keys used by Account Mappings and Posting Rules UI.
 * Keep in sync with AccountMappingsSeeder and posting-rule resolution keys.
 */
final class AccountMappingKeys
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'sales_revenue',
            'sales_return',
            'bad_debt_expense',
            'cash_on_hand',
            'bank_account',
            'petty_cash',
            'payment_method_account',
            'accounts_receivable',
            'accounts_payable',
            'inventory_asset',
            'cogs',
            'inventory_adjustment',
            'inventory_write_off',
            'output_tax',
            'input_tax',
            'tax_payable',
            'retained_earnings',
            'opening_balance_equity',
            'suspense_account',
            'rounding_difference',
            'cheques_in_hand',
            'cheques_deposited',
            'cheques_payable',
            'fixed_asset',
            'accumulated_depreciation',
            'depreciation_expense',
            'dishonour_expense',
            'gain_on_disposal',
            'loss_on_disposal',
            // Phase 12 — expenses / payroll
            'expense_default',
            'payroll_expense',
            'overtime_expense',
            'employer_contribution_expense',
            'net_salary_payable',
            'tax_withheld_payable',
            'employee_advance_receivable',
            'reimbursement_payable',
        ];
    }
}
