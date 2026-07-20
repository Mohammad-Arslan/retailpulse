<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateDebitNoteData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreDebitNoteRequest;
use App\Models\DebitNote;
use App\Services\Accounting\DebitNoteListService;
use App\Services\Accounting\DebitNoteService;
use App\Services\Procurement\DebitNotePdfService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DebitNoteController extends Controller
{
    public function __construct(
        private readonly DebitNoteService $debitNoteService,
        private readonly DebitNoteListService $debitNoteListService,
        private readonly DebitNotePdfService $debitNotePdf,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DebitNote::class);

        $filters = ListPagination::filters($request, ['search', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        return Inertia::render('Admin/Accounting/DebitNotes/Index', [
            'debitNotes' => $this->debitNoteListService->paginateIndex($filters, $perPage),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', DebitNote::class);

        return Inertia::render('Admin/Accounting/DebitNotes/Create', $this->debitNoteListService->createFormPayload());
    }

    public function store(StoreDebitNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', DebitNote::class);

        $debitNote = $this->debitNoteService->create(
            CreateDebitNoteData::fromRequest($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.accounting.debit-notes.index')
            ->with('success', __('Debit note :ref created.', ['ref' => $debitNote->reference_no]));
    }

    public function pdf(DebitNote $debitNote): BinaryFileResponse
    {
        $this->authorize('view', $debitNote);

        $path = $this->debitNotePdf->generate($debitNote);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$debitNote->reference_no.'.pdf"',
        ]);
    }
}
