<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateCreditNoteData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreCreditNoteRequest;
use App\Models\CreditNote;
use App\Services\Accounting\CreditNoteListService;
use App\Services\Accounting\CreditNoteService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteService $creditNoteService,
        private readonly CreditNoteListService $creditNoteListService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CreditNote::class);

        $filters = ListPagination::filters($request, ['search', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        return Inertia::render('Admin/Accounting/CreditNotes/Index', [
            'creditNotes' => $this->creditNoteListService->paginateIndex($filters, $perPage),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CreditNote::class);

        return Inertia::render('Admin/Accounting/CreditNotes/Create', $this->creditNoteListService->createFormPayload());
    }

    public function store(StoreCreditNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', CreditNote::class);

        $creditNote = $this->creditNoteService->create(
            CreateCreditNoteData::fromRequest($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.accounting.credit-notes.index')
            ->with('success', __('Credit note :number created.', ['number' => $creditNote->credit_note_number]));
    }
}
