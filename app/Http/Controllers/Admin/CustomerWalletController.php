<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TopUpCustomerWalletRequest;
use App\Models\Customer;
use App\Services\Customer\WalletService;
use Illuminate\Http\RedirectResponse;

final class CustomerWalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
    ) {}

    public function topUp(TopUpCustomerWalletRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->wallet->topUp(
            customerId: $customer->id,
            amount: (float) $request->validated('amount'),
            userId: $request->user()->id,
            meta: array_filter(['notes' => $request->validated('notes')]),
            paymentMethod: $request->validated('payment_method'),
        );

        return back()->with('success', __('Wallet topped up successfully.'));
    }
}
