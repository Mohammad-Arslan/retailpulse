<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateBankAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Services\Accounting\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        return Inertia::render('Admin/Accounting/BankAccounts/Index', $this->bankAccountService->indexPayload());
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $this->bankAccountService->create(CreateBankAccountData::fromRequest($request));

        return back()->with('success', __('Bank account created successfully.'));
    }
}
