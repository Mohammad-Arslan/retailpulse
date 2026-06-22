<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsurePosAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetBranchContext;
use App\Http\Middleware\SetLocale;
use App\Jobs\BuildArAgingSnapshotsJob;
use App\Jobs\CreateScheduledCountSessionsJob;
use App\Jobs\Procurement\PoApprovalEscalationJob;
use App\Jobs\Procurement\SupplierPerformanceScoringJob;
use App\Jobs\RecalculateLoyaltyTiersJob;
use App\Jobs\SendOverdueRemindersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'branch.context' => SetBranchContext::class,
            'pos.access' => EnsurePosAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('import-export:prune')->dailyAt('02:00');
        $schedule->command('inventory:release-expired-reservations')->everyMinute();
        $schedule->job(CreateScheduledCountSessionsJob::class)->dailyAt('01:00');
        $schedule->job(BuildArAgingSnapshotsJob::class)->dailyAt('02:30');
        $schedule->job(RecalculateLoyaltyTiersJob::class)->dailyAt('03:00');
        $schedule->job(SendOverdueRemindersJob::class)->dailyAt('08:00');
        $schedule->job(SupplierPerformanceScoringJob::class)->monthlyOn(1, '04:00');
        $schedule->job(PoApprovalEscalationJob::class)->hourly();
    })->create();
