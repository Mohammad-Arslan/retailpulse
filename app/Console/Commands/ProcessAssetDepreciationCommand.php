<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\AssetDepreciationService;
use Illuminate\Console\Command;

final class ProcessAssetDepreciationCommand extends Command
{
    protected $signature = 'accounting:process-depreciation {--date=}';

    protected $description = 'Post monthly depreciation for active fixed assets';

    public function handle(AssetDepreciationService $depreciation): int
    {
        $asOf = $this->option('date') ?: now()->toDateString();
        $processed = $depreciation->processMonthly($asOf);

        $this->info("Processed depreciation for {$processed->count()} asset(s) as of {$asOf}.");

        return self::SUCCESS;
    }
}
