<?php

use App\Http\Middleware\EnsureAccountingModuleEnabled;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureHrModuleEnabled;
use App\Http\Middleware\EnsureLocalEnvironment;
use App\Http\Middleware\EnsurePosAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetBranchContext;
use App\Http\Middleware\SetLocale;
use App\Jobs\BuildArAgingSnapshotsJob;
use App\Jobs\CreateScheduledCountSessionsJob;
use App\Jobs\ProcessLoyaltyExpiryJob;
use App\Jobs\Procurement\PoApprovalEscalationJob;
use App\Jobs\Procurement\PriceListExpiryAlertJob;
use App\Jobs\Procurement\SupplierPerformanceScoringJob;
use App\Jobs\RecalculateLoyaltyTiersJob;
use App\Jobs\SendOverdueRemindersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'accounting-module' => EnsureAccountingModuleEnabled::class,
            'hr-module' => EnsureHrModuleEnabled::class,
            'local' => EnsureLocalEnvironment::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $wantsJson = static fn ($request): bool => $request->expectsJson()
            || $request->ajax()
            || str_contains((string) $request->header('Accept'), 'text/event-stream');

        // Every 403 source ends up implementing HttpExceptionInterface: a status-less
        // AuthorizationException (policy/gate denial) is converted to AccessDeniedHttpException by
        // Laravel's own Handler::prepareException() before renderables run, and abort(403) (used by
        // EnsureAdminAccess, EnsureHrModuleEnabled, EnsureAccountingModuleEnabled, EnsurePosAccess,
        // etc.) throws a plain HttpException — so typing against the interface and filtering on
        // status code catches every 403 uniformly instead of one hardcoded exception/message.
        // Without this, an admin/Inertia request that hits any of those denials falls through to
        // Laravel's default HTML error page, which Inertia can't render inline — the client shows a
        // jarring full-page modal instead of the intended redirect-back-with-toast experience.
        $exceptions->renderable(function (HttpExceptionInterface $e, Request $request) use ($wantsJson) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            $message = $e->getMessage() !== '' ? $e->getMessage() : __('You do not have permission to access this page.');

            if ($wantsJson($request) && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()
                ->back(fallback: route('admin.dashboard'))
                ->with('error', $message);
        });

        $exceptions->renderable(function (InsufficientCreditsException $e, $request) use ($wantsJson) {
            if ($wantsJson($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 402);
            }

            return null;
        });

        $exceptions->renderable(function (ConnectionException $e, $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            $url = (string) data_get(config('ai.providers.'.config('ai.default')), 'url', 'http://127.0.0.1:11434');

            return response()->json([
                'message' => 'Could not connect to the AI provider. Ensure Ollama is running at '.$url.'.',
            ], 503);
        });

        $exceptions->renderable(function (RequestException $e, $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            $status = $e->response?->status() ?? 502;
            $body = strtolower((string) ($e->response?->body() ?? ''));
            $model = (string) data_get(
                config('ai.providers.'.config('ai.default')),
                'models.text.default',
                env('OLLAMA_MODEL', 'qwen2.5-coder:7b'),
            );

            if ($status === 401) {
                return response()->json([
                    'message' => 'AI provider authentication failed. Check API key / credentials in .env.',
                ], 401);
            }

            if ($status === 404 || str_contains($body, 'not found') || str_contains($body, 'model')) {
                return response()->json([
                    'message' => "AI model [{$model}] was not found. Run: ollama pull {$model}",
                ], 404);
            }

            return response()->json([
                'message' => 'AI request failed'.($status ? " (HTTP {$status})" : '').'.',
            ], $status >= 400 && $status < 600 ? $status : 502);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('import-export:prune')->dailyAt('02:00');
        $schedule->command('inventory:release-expired-reservations')->everyMinute();
        $schedule->job(CreateScheduledCountSessionsJob::class)->dailyAt('01:00');
        $schedule->job(BuildArAgingSnapshotsJob::class)->dailyAt('02:30');
        $schedule->job(RecalculateLoyaltyTiersJob::class)->dailyAt('03:00');
        $schedule->job(ProcessLoyaltyExpiryJob::class)->dailyAt('03:30');
        $schedule->job(SendOverdueRemindersJob::class)->dailyAt('08:00');
        $schedule->job(SupplierPerformanceScoringJob::class)->monthlyOn(1, '04:00');
        $schedule->job(PoApprovalEscalationJob::class)->hourly();
        $schedule->job(PriceListExpiryAlertJob::class)->dailyAt('07:00');
        $schedule->command('accounting:process-depreciation')->monthlyOn(1, '05:00');
        $schedule->command('expenses:process-recurring')->dailyAt('06:00');
        $schedule->command('leave:process-year-end')->dailyAt('01:30');
        $schedule->command('leave:process-accrual')->dailyAt('01:45');
        $schedule->command('toil:expire-credits')->dailyAt('02:00');
        $schedule->command('toil:reconcile-balances')->dailyAt('02:15');
    })->create();
