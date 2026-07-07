<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use App\Models\ChartOfAccount;
use App\Models\TaxType;
use Illuminate\Database\Seeder;

final class TaxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $outputTaxAccountId = ChartOfAccount::query()->where('code', '2200')->value('id');
        $inputTaxAccountId = ChartOfAccount::query()->where('code', '1350')->value('id');

        $types = [
            ['name' => 'GST 5%', 'code' => 'GST5', 'rate' => 5.00, 'direction' => TaxDirection::Both, 'method' => TaxCalculationMethod::Exclusive],
            ['name' => 'GST 10%', 'code' => 'GST10', 'rate' => 10.00, 'direction' => TaxDirection::Both, 'method' => TaxCalculationMethod::Exclusive],
            ['name' => 'VAT 20%', 'code' => 'VAT20', 'rate' => 20.00, 'direction' => TaxDirection::Both, 'method' => TaxCalculationMethod::Exclusive],
            ['name' => 'US Sales Tax 7%', 'code' => 'ST7', 'rate' => 7.00, 'direction' => TaxDirection::Sales, 'method' => TaxCalculationMethod::Exclusive],
            ['name' => 'Zero Rated', 'code' => 'ZERO', 'rate' => 0.00, 'direction' => TaxDirection::Both, 'method' => TaxCalculationMethod::Exclusive],
            ['name' => 'WHT 5%', 'code' => 'WHT5', 'rate' => 5.00, 'direction' => TaxDirection::Purchase, 'method' => TaxCalculationMethod::Exclusive, 'recoverable' => 0.00],
            ['name' => 'WHT 10%', 'code' => 'WHT10', 'rate' => 10.00, 'direction' => TaxDirection::Purchase, 'method' => TaxCalculationMethod::Exclusive, 'recoverable' => 50.00],
        ];

        foreach ($types as $type) {
            TaxType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'rate' => $type['rate'],
                    'tax_direction' => $type['direction'],
                    'calculation_method' => $type['method'],
                    'output_tax_account_id' => $outputTaxAccountId,
                    'input_tax_account_id' => $inputTaxAccountId,
                    'tax_payable_account_id' => $outputTaxAccountId,
                    'recoverable_percentage' => $type['recoverable'] ?? 100.00,
                    'effective_from' => '2020-01-01',
                    'status' => 'active',
                ],
            );
        }
    }
}
