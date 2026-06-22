<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TopUpCustomerWalletRequest;
use App\Models\Customer;
use App\Services\Customer\WalletService;
use Illuminate\Http\JsonResponse;

final class CustomerWalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
    ) {}

    public function topUp(TopUpCustomerWalletRequest $request, int $customer): JsonResponse
    {
        $record = Customer::query()->findOrFail($customer);

        $wallet = $this->wallet->topUp(
            customerId: $record->id,
            amount: (float) $request->validated('amount'),
            userId: $request->user()->id,
            meta: array_filter(['notes' => $request->validated('notes')]),
            paymentMethod: $request->validated('payment_method'),
        );

        return response()->json([
            'customer_id' => $record->id,
            'balance' => number_format((float) $wallet->balance, 2, '.', ''),
            'expires_at' => $wallet->expires_at?->toIso8601String(),
        ]);
    }
}
