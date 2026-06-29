<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Loyalty\AdjustLoyaltyPointsData;
use App\DTOs\Loyalty\ApproveLoyaltyTransactionData;
use App\DTOs\Loyalty\RejectLoyaltyTransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loyalty\AdjustLoyaltyPointsRequest;
use App\Http\Requests\Admin\Loyalty\ApproveLoyaltyTransactionRequest;
use App\Http\Requests\Admin\Loyalty\RejectLoyaltyTransactionRequest;
use App\Models\Customer;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyApprovalService;
use App\Services\Loyalty\LoyaltyRedemptionService;
use App\Services\Loyalty\LoyaltyWalletService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LoyaltyTransactionController extends Controller
{
    public function __construct(
        private readonly LoyaltyWalletService $wallets,
        private readonly LoyaltyRedemptionService $redemption,
        private readonly LoyaltyApprovalService $approvals,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewTransactions', LoyaltyProgram::class);

        $branchId = app(BranchContext::class)->branchId;

        $query = CustomerLoyaltyTransaction::query()
            ->with(['customer:id,name', 'program:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $transactions = $query->limit(100)->get()->map(fn (CustomerLoyaltyTransaction $tx) => [
            'id' => $tx->id,
            'customer' => $tx->customer?->name,
            'program' => $tx->program?->name,
            'transaction_type' => $tx->transaction_type->value,
            'points' => $tx->points,
            'status' => $tx->status->value,
            'reason' => $tx->reason,
            'created_at' => $tx->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/Loyalty/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => ['status' => $request->query('status')],
        ]);
    }

    public function adjust(AdjustLoyaltyPointsRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('adjustPoints', LoyaltyProgram::class);

        $data = AdjustLoyaltyPointsData::fromRequest($request);
        $program = LoyaltyProgram::query()->findOrFail($data->programId);
        $branchId = app(BranchContext::class)->branchId;

        $wallet = $this->wallets->getOrCreateWallet(
            $customer->id,
            $program,
            $program->earn_scope->value === 'branch' ? $branchId : null,
        );

        $this->redemption->adjustPoints(
            $wallet,
            $data->points,
            $data->reason,
            (int) $request->user()->id,
            $program,
        );

        return back()->with('success', __('Loyalty points adjusted successfully.'));
    }

    public function approve(ApproveLoyaltyTransactionRequest $request, CustomerLoyaltyTransaction $transaction): RedirectResponse
    {
        $data = ApproveLoyaltyTransactionData::fromRequest($request);

        $this->approvals->approveWithPin(
            $transaction,
            $request->user(),
            $data->pin,
        );

        return back()->with('success', __('Loyalty transaction approved.'));
    }

    public function reject(RejectLoyaltyTransactionRequest $request, CustomerLoyaltyTransaction $transaction): RedirectResponse
    {
        $data = RejectLoyaltyTransactionData::fromRequest($request);

        $this->approvals->reject($transaction, $request->user(), $data->reason);

        return back()->with('success', __('Loyalty transaction rejected.'));
    }
}
