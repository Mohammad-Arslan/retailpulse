<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateChequeData;
use App\DTOs\Accounting\UpdateChequeStatusData;
use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreChequeRequest;
use App\Http\Requests\Admin\Accounting\UpdateChequeStatusRequest;
use App\Models\Cheque;
use App\Services\Accounting\ChequeListService;
use App\Services\Accounting\ChequeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ChequeController extends Controller
{
    public function __construct(
        private readonly ChequeService $chequeService,
        private readonly ChequeListService $chequeListService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Cheque::class);

        return Inertia::render('Admin/Accounting/Cheques/Index', [
            ...$this->chequeListService->indexPayload(),
            'chequeTypes' => ChequeType::values(),
            'chequeStatuses' => ChequeStatus::values(),
        ]);
    }

    public function store(StoreChequeRequest $request): RedirectResponse
    {
        $this->authorize('create', Cheque::class);

        $this->chequeService->create(
            CreateChequeData::fromRequest($request),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Cheque recorded successfully.'));
    }

    public function updateStatus(UpdateChequeStatusRequest $request, Cheque $cheque): RedirectResponse
    {
        $this->authorize('update', $cheque);

        $this->chequeService->updateStatus(
            $cheque,
            UpdateChequeStatusData::fromRequest($request),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Cheque status updated.'));
    }
}
