<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Checkout;

use App\DTOs\Checkout\ConfirmCheckoutData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\ConfirmCheckoutRequest;
use App\Models\PosCart;
use App\Models\Sale;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    public function show(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);

        return response()->json($this->checkout->bootstrap($cart));
    }

    public function confirm(ConfirmCheckoutRequest $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);

        $sale = $this->checkout->confirm(
            cart: $cart,
            data: new ConfirmCheckoutData(
                customerId: $request->validated('customer_id'),
                notes: $request->validated('notes'),
            ),
            cashierId: $request->user()->id,
        );

        return response()->json($this->formatSale($sale), Response::HTTP_CREATED);
    }

    public function abandon(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->checkout->abandonCheckout($cart);

        return response()->json([
            'cart_id' => $cart->id,
            'status' => $cart->status?->value,
        ]);
    }

    private function findOwnedCart(Request $request, string $cartId): PosCart
    {
        return PosCart::query()
            ->where('cashier_id', $request->user()->id)
            ->findOrFail($cartId);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSale(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'status' => $sale->status?->value,
            'balance_due' => number_format((float) $sale->balance_due, 2, '.', ''),
            'grand_total' => number_format((float) $sale->grand_total, 2, '.', ''),
            'tax_total' => number_format((float) $sale->tax_total, 2, '.', ''),
            'currency' => $sale->currency,
            'items' => $sale->items->map(fn ($item) => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total_inc_tax' => number_format((float) $item->line_total_inc_tax, 2, '.', ''),
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method?->value,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'status' => $payment->status?->value,
                'meta' => $payment->meta,
            ])->all(),
        ];
    }
}
