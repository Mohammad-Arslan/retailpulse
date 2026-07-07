<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountingEventStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountingEvent;
use App\Services\Accounting\AccountingEventListService;
use App\Services\Accounting\AccountingEventService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class AccountingEventController extends Controller
{
    public function __construct(
        private readonly AccountingEventService $eventService,
        private readonly AccountingEventListService $eventListService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);

        $filters = ListPagination::filters($request, ['search', 'event_type', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $result = $this->eventListService->paginateIndex($filters, $perPage);

        return Inertia::render('Admin/Accounting/Events/Index', [
            'events' => $result['paginator'],
            'filters' => $result['filters'],
            'statuses' => AccountingEventStatus::values(),
        ]);
    }

    public function retry(Request $request, AccountingEvent $accountingEvent): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('accounting.post-journal') || $request->user()?->can('accounting.view'),
            403,
        );

        if ($accountingEvent->processing_status !== AccountingEventStatus::Failed) {
            return back()->withErrors(['event' => __('Only failed events can be retried.')]);
        }

        try {
            $this->eventService->retry($accountingEvent, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }

        return back()->with('success', __('Accounting event retried successfully.'));
    }
}
