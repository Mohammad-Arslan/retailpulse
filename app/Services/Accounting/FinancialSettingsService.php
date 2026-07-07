<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\UpdateFinancialSettingsData;
use App\Models\FinancialSetting;

final class FinancialSettingsService
{
    public function get(): FinancialSetting
    {
        $settings = FinancialSetting::query()->first();

        if ($settings === null) {
            $settings = FinancialSetting::query()->create([
                'functional_currency_code' => 'USD',
                'fiscal_year_start_month' => 1,
                'default_inventory_valuation_method' => 'fifo',
            ]);
        }

        return $settings;
    }

    public function update(UpdateFinancialSettingsData $data): FinancialSetting
    {
        $settings = $this->get();
        $settings->update($data->toArray());

        return $settings->fresh();
    }
}
