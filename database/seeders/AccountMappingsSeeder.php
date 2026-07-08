<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

final class AccountMappingsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ChartOfAccount::query()
            ->whereIn('code', [
                '1100', '1110', '1210', '1300', '1350', '1400', '1500', '1510', '1610', '1620',
                '2100', '2200', '2300', '3400', '4100', '4150', '4200', '5100', '5500', '5600', '5900', '5910',
            ])
            ->pluck('id', 'code');

        $mappings = [
            ['mapping_key' => 'sales_revenue', 'account_code' => '4100'],
            ['mapping_key' => 'sales_return', 'account_code' => '4150'],
            ['mapping_key' => 'bad_debt_expense', 'account_code' => '5910'],
            ['mapping_key' => 'cash_on_hand', 'account_code' => '1100'],
            ['mapping_key' => 'accounts_receivable', 'account_code' => '1300'],
            ['mapping_key' => 'accounts_payable', 'account_code' => '2100'],
            ['mapping_key' => 'inventory_asset', 'account_code' => '1400'],
            ['mapping_key' => 'cogs', 'account_code' => '5100'],
            ['mapping_key' => 'inventory_adjustment', 'account_code' => '5910'],
            ['mapping_key' => 'inventory_write_off', 'account_code' => '5910'],
            ['mapping_key' => 'output_tax', 'account_code' => '2200'],
            ['mapping_key' => 'input_tax', 'account_code' => '1350'],
            ['mapping_key' => 'opening_balance_equity', 'account_code' => '3400'],
            ['mapping_key' => 'bank_account', 'account_code' => '1210'],
            ['mapping_key' => 'payment_method_account', 'account_code' => '1100', 'payment_method' => 'cash'],
            ['mapping_key' => 'payment_method_account', 'account_code' => '1210', 'payment_method' => 'card'],
            ['mapping_key' => 'payment_method_account', 'account_code' => '1210', 'payment_method' => 'bank_transfer'],
            ['mapping_key' => 'petty_cash', 'account_code' => '1110'],
            ['mapping_key' => 'cheques_in_hand', 'account_code' => '1500'],
            ['mapping_key' => 'cheques_deposited', 'account_code' => '1510'],
            ['mapping_key' => 'cheques_payable', 'account_code' => '2300'],
            ['mapping_key' => 'fixed_asset', 'account_code' => '1610'],
            ['mapping_key' => 'accumulated_depreciation', 'account_code' => '1620'],
            ['mapping_key' => 'depreciation_expense', 'account_code' => '5500'],
            ['mapping_key' => 'dishonour_expense', 'account_code' => '5900'],
            ['mapping_key' => 'gain_on_disposal', 'account_code' => '4200'],
            ['mapping_key' => 'loss_on_disposal', 'account_code' => '5600'],
        ];

        foreach ($mappings as $mapping) {
            $accountId = $accounts->get($mapping['account_code']);

            if ($accountId === null) {
                continue;
            }

            $attributes = [
                'account_id' => $accountId,
                'payment_method' => $mapping['payment_method'] ?? null,
                'status' => 'active',
                'priority' => 100,
            ];

            $unique = [
                'mapping_key' => $mapping['mapping_key'],
                'payment_method' => $mapping['payment_method'] ?? null,
            ];

            AccountMapping::query()->firstOrCreate($unique, $attributes);
        }
    }
}
