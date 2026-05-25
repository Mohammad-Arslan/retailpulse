<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\DTOs\Pos\AddCartItemData;
use App\DTOs\Pos\UpdateCartItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\AddCartItemRequest;
use App\Http\Requests\Api\Pos\UpdateCartItemRequest;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Services\PosCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CartItemController extends Controller
{
    public function __construct(
        private readonly PosCartService $cartService,
    ) {}

    public function store(AddCartItemRequest $request, string $cartId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $item = $this->cartService->addItem($cart, AddCartItemData::fromRequest($request));

        return response()->json($this->formatItem($item), Response::HTTP_CREATED);
    }

    public function update(UpdateCartItemRequest $request, string $cartId, int $itemId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $item = $this->findCartItem($cart, $itemId);

        $item = $this->cartService->updateItem($cart, $item, UpdateCartItemData::fromRequest($request));

        return response()->json($this->formatItem($item));
    }

    public function destroy(Request $request, string $cartId, int $itemId): JsonResponse
    {
        $cart = $this->findOwnedCart($request, $cartId);
        $item = $this->findCartItem($cart, $itemId);

        $this->cartService->removeItem($cart, $item);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function findOwnedCart(Request $request, string $cartId): PosCart
    {
        return PosCart::query()
            ->where('cashier_id', $request->user()->id)
            ->findOrFail($cartId);
    }

    private function findCartItem(PosCart $cart, int $itemId): PosCartItem
    {
        return PosCartItem::query()
            ->where('cart_id', $cart->id)
            ->findOrFail($itemId);
    }

    private function formatItem(PosCartItem $item): array
    {
        return $item->toPosArray();
    }
}
