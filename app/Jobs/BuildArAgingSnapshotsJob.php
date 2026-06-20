<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Customer\ArAgingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class BuildArAgingSnapshotsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ArAgingService $aging): void
    {
        $aging->buildSnapshots();
    }
}
