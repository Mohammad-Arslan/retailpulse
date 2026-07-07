<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\FinancialSetting;
use App\Models\TaxType;
use Illuminate\Database\Seeder;

final class FinancialSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ChartOfAccount::query()
            ->whereIn('code', ['3200', '3300', '3400'])
            ->pluck('id', 'code');

        FinancialSetting::query()->firstOrCreate(
            ['tenant_id' => null],
            [
                'functional_currency_code' => 'USD',
                'fiscal_year_start_month' => 1,
                'retained_earnings_account_id' => $accounts->get('3200'),
                'current_year_earnings_account_id' => $accounts->get('3300'),
                'opening_balance_equity_account_id' => $accounts->get('3400'),
                'default_sales_tax_type_id' => TaxType::query()->where('code', 'GST5')->value('id'),
                'default_purchase_tax_type_id' => TaxType::query()->where('code', 'GST5')->value('id'),
                'default_tax_type_id' => TaxType::query()->where('code', 'GST5')->value('id'),
                'fiscal_year_reopen_window_hours' => 48,
                'tax_reporting_enabled' => true,
                'tax_return_frequency' => 'monthly',
            ],
        );
    }
}
