<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\FiscalYearStatus;
use App\Models\FiscalYear;
use Database\Seeders\AccountMappingsSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PostingRulesSeeder;

trait SeedsAccounting
{
    protected function seedAccounting(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountMappingsSeeder::class);
        $this->seed(PostingRulesSeeder::class);

        if (FiscalYear::query()->count() === 0) {
            FiscalYear::query()->create([
                'name' => 'FY2020-2030',
                'start_date' => '2020-01-01',
                'end_date' => '2030-12-31',
                'status' => FiscalYearStatus::Open,
            ]);
        }
    }
}
