<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChartOfAccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $assets = $this->seedAccount('1000', 'Assets', ChartOfAccountType::Asset, null, true, 1);
        $this->seedAccount('1100', 'Cash on Hand', ChartOfAccountType::Asset, $assets->id, false, 2);
        $this->seedAccount('1110', 'Petty Cash', ChartOfAccountType::Asset, $assets->id, false, 2);

        $bankAccounts = $this->seedAccount('1200', 'Bank Accounts', ChartOfAccountType::Asset, $assets->id, true, 2);
        $this->seedAccount('1210', 'Operating Bank Account', ChartOfAccountType::Asset, $bankAccounts->id, false, 3);

        $this->seedAccount('1300', 'Accounts Receivable', ChartOfAccountType::Asset, $assets->id, false, 2);
        $this->seedAccount('1350', 'Input Tax Recoverable', ChartOfAccountType::Asset, $assets->id, false, 2);
        $this->seedAccount('1400', 'Inventory', ChartOfAccountType::Asset, $assets->id, false, 2);
        $this->seedAccount('1500', 'Cheques in Hand', ChartOfAccountType::Asset, $assets->id, false, 2);
        $this->seedAccount('1510', 'Cheques Deposited', ChartOfAccountType::Asset, $assets->id, false, 2);

        $fixedAssets = $this->seedAccount('1600', 'Fixed Assets', ChartOfAccountType::Asset, $assets->id, true, 2);
        $this->seedAccount('1610', 'Fixed Assets', ChartOfAccountType::Asset, $fixedAssets->id, false, 3);
        $this->seedAccount('1620', 'Accumulated Depreciation', ChartOfAccountType::Asset, $fixedAssets->id, false, 3);

        $liabilities = $this->seedAccount('2000', 'Liabilities', ChartOfAccountType::Liability, null, true, 1);
        $this->seedAccount('2100', 'Accounts Payable', ChartOfAccountType::Liability, $liabilities->id, false, 2);
        $this->seedAccount('2200', 'Tax Collected / Output VAT-GST', ChartOfAccountType::Liability, $liabilities->id, false, 2);
        $this->seedAccount('2210', 'Tax Payable', ChartOfAccountType::Liability, $liabilities->id, false, 2);
        $this->seedAccount('2300', 'Cheques Payable', ChartOfAccountType::Liability, $liabilities->id, false, 2);
        $this->seedAccount('2400', 'Intercompany Payable', ChartOfAccountType::Liability, $liabilities->id, false, 2);

        $equity = $this->seedAccount('3000', 'Equity', ChartOfAccountType::Equity, null, true, 1);
        $this->seedAccount('3100', 'Owner Capital', ChartOfAccountType::Equity, $equity->id, false, 2);
        $this->seedAccount('3200', 'Retained Earnings', ChartOfAccountType::Equity, $equity->id, false, 2);
        $this->seedAccount('3300', 'Current Year Earnings', ChartOfAccountType::Equity, $equity->id, false, 2);
        $this->seedAccount('3400', 'Opening Balance Equity', ChartOfAccountType::Equity, $equity->id, false, 2);

        $revenue = $this->seedAccount('4000', 'Revenue', ChartOfAccountType::Revenue, null, true, 1);
        $this->seedAccount('4100', 'Sales Revenue', ChartOfAccountType::Revenue, $revenue->id, false, 2);
        $this->seedAccount('4150', 'Sales Returns', ChartOfAccountType::Revenue, $revenue->id, false, 2);
        $this->seedAccount('4200', 'Other Income', ChartOfAccountType::Revenue, $revenue->id, false, 2);
        $this->seedAccount('4300', 'FX Gain', ChartOfAccountType::Revenue, $revenue->id, false, 2);

        $expenses = $this->seedAccount('5000', 'Expenses', ChartOfAccountType::Expense, null, true, 1);
        $this->seedAccount('5100', 'Cost of Goods Sold', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5200', 'Payroll Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5300', 'Rent Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5400', 'Utilities Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5500', 'Depreciation Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5600', 'Scrapped Goods Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5700', 'Inventory Adjustment Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5800', 'Bank Charges', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5900', 'Dishonour Charges', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5910', 'Bad Debt Expense', ChartOfAccountType::Expense, $expenses->id, false, 2);
        $this->seedAccount('5950', 'FX Loss', ChartOfAccountType::Expense, $expenses->id, false, 2);
    }

    private function seedAccount(
        string $code,
        string $name,
        ChartOfAccountType $type,
        ?int $parentId,
        bool $isGroup,
        int $level,
    ): ChartOfAccount {
        return ChartOfAccount::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'account_level' => $level,
                'is_group' => $isGroup,
                'is_postable' => ! $isGroup,
                'status' => 'active',
            ],
        );
    }
}
