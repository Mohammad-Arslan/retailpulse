<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreatePettyCashRegisterData;
use App\Enums\PettyCashRegisterMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StorePettyCashRegisterRequest;
use App\Models\PettyCashRegister;
use App\Services\Accounting\PettyCashRegisterService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PettyCashController extends Controller
{
    public function __construct(
        private readonly PettyCashRegisterService $pettyCashRegisterService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', PettyCashRegister::class);

        return Inertia::render('Admin/Accounting/PettyCash/Index', [
            ...$this->pettyCashRegisterService->indexPayload(),
            'registerModes' => PettyCashRegisterMode::values(),
        ]);
    }

    public function storeRegister(StorePettyCashRegisterRequest $request): RedirectResponse
    {
        $this->authorize('create', PettyCashRegister::class);

        $this->pettyCashRegisterService->create(CreatePettyCashRegisterData::fromRequest($request));

        return back()->with('success', __('Petty cash register created successfully.'));
    }
}
