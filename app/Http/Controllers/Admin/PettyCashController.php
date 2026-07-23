<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreatePettyCashRegisterData;
use App\Enums\PettyCashApprovalStatus;
use App\Enums\PettyCashRegisterMode;
use App\Enums\PettyCashVoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\RejectPettyCashVoucherRequest;
use App\Http\Requests\Admin\Accounting\StorePettyCashRegisterRequest;
use App\Http\Requests\Admin\Accounting\StorePettyCashVoucherRequest;
use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use App\Services\Accounting\PettyCashApprovalService;
use App\Services\Accounting\PettyCashRegisterService;
use App\Services\Accounting\PettyCashService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PettyCashController extends Controller
{
    public function __construct(
        private readonly PettyCashRegisterService $pettyCashRegisterService,
        private readonly PettyCashService $pettyCashService,
        private readonly PettyCashApprovalService $pettyCashApprovalService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', PettyCashRegister::class);

        return Inertia::render('Admin/Accounting/PettyCash/Index', [
            ...$this->pettyCashRegisterService->indexPayload(),
            'registerModes' => PettyCashRegisterMode::values(),
            'voucherTypes' => PettyCashVoucherType::values(),
        ]);
    }

    public function storeRegister(StorePettyCashRegisterRequest $request): RedirectResponse
    {
        $this->authorize('create', PettyCashRegister::class);

        $this->pettyCashRegisterService->create(CreatePettyCashRegisterData::fromRequest($request));

        return back()->with('success', __('Petty cash register created successfully.'));
    }

    public function storeVoucher(
        StorePettyCashVoucherRequest $request,
        PettyCashRegister $pettyCashRegister,
    ): RedirectResponse {
        $this->authorize('create', PettyCashVoucher::class);

        $voucher = $this->pettyCashService->createVoucher(
            $pettyCashRegister,
            $request->validated(),
            (int) $request->user()->id,
        );

        $pending = $voucher->approval_status === PettyCashApprovalStatus::Pending;

        return back()->with(
            'success',
            $pending
                ? __('Voucher created, pending approval.')
                : __('Voucher created and posted.'),
        );
    }

    public function approveVoucher(PettyCashVoucher $pettyCashVoucher): RedirectResponse
    {
        $this->authorize('approve', $pettyCashVoucher);

        try {
            $this->pettyCashApprovalService->approve($pettyCashVoucher, request()->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Voucher approved and posted.'));
    }

    public function rejectVoucher(
        RejectPettyCashVoucherRequest $request,
        PettyCashVoucher $pettyCashVoucher,
    ): RedirectResponse {
        $this->authorize('reject', $pettyCashVoucher);

        try {
            $this->pettyCashApprovalService->reject($pettyCashVoucher, $request->validated('reason'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Voucher rejected.'));
    }
}
