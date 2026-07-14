<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateJournalEntryData;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Admin\Accounting\UpdateJournalEntryRequest;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\JournalEntryRepositoryInterface;
use App\Services\Accounting\JournalService;
use App\Support\BranchOperationalOptions;
use App\Support\JournalEntryPresenter;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly JournalEntryRepositoryInterface $journalEntryRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'from', 'to', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $paginator = $this->journalEntryRepository
            ->paginate($filters, $perPage)
            ->through(fn (JournalEntry $entry) => JournalEntryPresenter::forList($entry));

        return Inertia::render('Admin/Accounting/JournalEntries/Index', [
            'journalEntries' => $paginator,
            'filters' => $filters,
            'journalStatuses' => JournalEntryStatus::values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JournalEntry::class);

        $accounts = $this->chartOfAccountRepository->postableOptions();

        return Inertia::render('Admin/Accounting/JournalEntries/Create', [
            'accounts' => $accounts,
            'postableAccounts' => $accounts,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'fiscalYears' => FiscalYear::query()
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);

        $data = CreateJournalEntryData::fromRequest($request);

        $entry = $this->journalService->createDraft(
            $data->attributes(),
            $data->lineArrays(),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.accounting.journal-entries.show', $entry)
            ->with('success', __('Journal entry saved as draft.'));
    }

    public function edit(JournalEntry $journalEntry): Response
    {
        $this->authorize('update', $journalEntry);

        $entry = $this->journalEntryRepository->findById($journalEntry->id);

        abort_if($entry === null, 404);

        return Inertia::render('Admin/Accounting/JournalEntries/Edit', [
            'journalEntry' => JournalEntryPresenter::forDetail($entry),
            'accounts' => $this->chartOfAccountRepository->postableOptions(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'fiscalYears' => FiscalYear::query()
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('update', $journalEntry);

        $data = CreateJournalEntryData::fromRequest($request);

        try {
            $entry = $this->journalService->updateDraft(
                $journalEntry,
                $data->attributes(),
                $data->lineArrays(),
                (int) $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.journal-entries.show', $entry)
            ->with('success', __('Journal entry updated.'));
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('delete', $journalEntry);

        try {
            $this->journalService->deleteDraft($journalEntry);
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.journal-entries.index')
            ->with('success', __('Draft journal entry deleted.'));
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $this->authorize('view', $journalEntry);

        $entry = $this->journalEntryRepository->findById($journalEntry->id);

        abort_if($entry === null, 404);

        return Inertia::render('Admin/Accounting/JournalEntries/Show', [
            'journalEntry' => JournalEntryPresenter::forDetail($entry),
        ]);
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('post', $journalEntry);

        try {
            $this->journalService->post($journalEntry, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return back()->with('success', __('Journal entry posted successfully.'));
    }

    public function reverse(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('reverse', $journalEntry);

        try {
            $reversal = $this->journalService->reverse(
                $journalEntry,
                (int) $request->user()->id,
                $request->input('description'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.journal-entries.show', $reversal)
            ->with('success', __('Journal entry reversed successfully.'));
    }

    public function approve(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('approve', $journalEntry);

        try {
            $this->journalService->approve($journalEntry, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return back()->with('success', __('Journal entry approved successfully.'));
    }
}
