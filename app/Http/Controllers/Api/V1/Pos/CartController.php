<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Enums\PosCartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\StoreCartRequest;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Services\PosCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CartController extends Controller
{
    public function __construct(
        private readonly PosCartService $cartService,
    ) {}

    public function store(StoreCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->createCart(
            cashierId: $request->user()->id,
            branchId: (int) $request->validated('branch_id'),
        );

        return response()->json($this->formatCart($cart->load('items')), Response::HTTP_CREATED);
    }

    public function show(Request $request, string $cartId): JsonResponse
    {
        $cart = PosCart::query()
            ->with(['items.variant', 'items.product', 'cashier', 'branch'])
            ->where('cashier_id', $request->user()->id)
            ->findOrFail($cartId);

        return response()->json($this->formatCart($cart));
    }

    public function index(Request $request): JsonResponse
    {
        $carts = PosCart::query()
            ->with(['items'])
            ->where('cashier_id', $request->user()->id)
            ->whereIn('status', [
                PosCartStatus::Active->value,
                PosCartStatus::Suspended->value,
                PosCartStatus::Completing->value,
            ])
            ->orderBy('slot')
            ->get();

        return response()->json($carts->map(fn ($cart) => $this->formatCart($cart)));
    }

    public function suspend(Request $request, string $cartId): JsonResponse
    {
        $this->authorize('pos.suspend-cart');

        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->cartService->suspendCart($cart);

        return response()->json($this->formatCart($cart));
    }

    public function resume(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->cartService->resumeCart($cart->load('items'));

        return response()->json($this->formatCart($cart));
    }

    public function void(Request $request, string $cartId): JsonResponse
    {
        $this->authorize('pos.void-cart');

        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->cartService->voidCart($cart);

        return response()->json($this->formatCart($cart));
    }

    public function complete(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->cartService->completeCart($cart);

        return response()->json($this->formatCart($cart));
    }

    public function reopen(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $cart = $this->cartService->reopenCart($cart->load('items'));

        return response()->json($this->formatCart($cart));
    }

    public function checkout(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $payload = $this->cartService->checkoutCart($cart->load('items'));

        return response()->json($payload);
    }

    public function stockWarnings(Request $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $warnings = $this->cartService->validateStockForCart($cart);

        return response()->json(['warnings' => $warnings]);
    }

    private function findOwnedCart(Request $request, string $cartId): PosCart
    {
        return PosCart::query()
            ->where('cashier_id', $request->user()->id)
            ->findOrFail($cartId);
    }

    private function formatCart(PosCart $cart): array
    {
        return [
            'id' => $cart->id,
            'cashier_id' => $cart->cashier_id,
            'branch_id' => $cart->branch_id,
            'status' => $cart->status?->value,
            'slot' => $cart->slot,
            'notes' => $cart->notes,
            'suspended_at' => $cart->suspended_at?->toIso8601String(),
            'items' => $cart->relationLoaded('items')
                ? $cart->items->map(fn (PosCartItem $item) => $item->toPosArray())->all()
                : [],
            'created_at' => $cart->created_at?->toIso8601String(),
            'updated_at' => $cart->updated_at?->toIso8601String(),
        ];
    }
}
