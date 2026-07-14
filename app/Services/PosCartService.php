<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Inventory\ReserveStockData;
use App\DTOs\Pos\AddCartItemData;
use App\DTOs\Pos\UpdateCartItemData;
use App\Enums\PosCartStatus;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\PosCartRepositoryInterface;
use App\Support\Pos\PosBranchWarehouses;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosCartService
{
    private const MAX_CARTS = 5;

    private const DISCOUNT_APPROVAL_THRESHOLD = 30.0;

    private const RESERVATION_REFERENCE_TYPE = 'pos_cart_item';

    public function __construct(
        private readonly PosCartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly InventoryService $inventory,
    ) {}

    public function createCart(int $cashierId, int $branchId): PosCart
    {
        $openCount = $this->carts->countOpenCartsForCashier($cashierId);

        if ($openCount >= self::MAX_CARTS) {
            throw ValidationException::withMessages([
                'carts' => __('You have reached the maximum of :max concurrent carts. Please complete or void an existing cart.', ['max' => self::MAX_CARTS]),
            ]);
        }

        $slot = $this->carts->nextAvailableSlot($cashierId);

        if ($slot === null) {
            throw ValidationException::withMessages([
                'carts' => __('No available cart slot. Please complete or void an existing cart.'),
            ]);
        }

        return $this->carts->create([
            'cashier_id' => $cashierId,
            'branch_id' => $branchId,
            'status' => PosCartStatus::Active,
            'slot' => $slot,
        ]);
    }

    public function addItem(PosCart $cart, AddCartItemData $data): PosCartItem
    {
        $this->assertCartEditable($cart);

        $variant = ProductVariant::query()
            ->with(['product', 'branchPrices' => fn ($q) => $q->where('branch_id', $cart->branch_id)])
            ->findOrFail($data->productVariantId);

        $tracksInventory = $this->shouldReserve($variant);

        if ($tracksInventory) {
            $available = $this->availableStock($cart->branch_id, $data->productVariantId);

            if ($available <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('This product is out of stock.'),
                ]);
            }

            if ($data->quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :n units available.', ['n' => $available]),
                ]);
            }
        }

        $unitPrice = $this->resolvePrice($variant, $cart->branch_id);

        $existingItem = $cart->items()
            ->where('product_variant_id', $data->productVariantId)
            ->first();

        if ($existingItem !== null) {
            $newQuantity = $existingItem->quantity + $data->quantity;

            if ($tracksInventory) {
                $availableForLine = $this->availableForLine($cart, $existingItem, $data->productVariantId);

                if ($newQuantity > $availableForLine) {
                    throw ValidationException::withMessages([
                        'quantity' => __('Only :n units available.', ['n' => $availableForLine]),
                    ]);
                }
            }

            return $this->updateItem($cart, $existingItem, new UpdateCartItemData(
                quantity: $newQuantity,
                discountType: null,
                discountValue: null,
                notes: $data->notes ?? $existingItem->notes,
                approvedByUserId: false,
                approverId: null,
                discountProvided: false,
            ));
        }

        $lineTotal = PosCartItem::computeLineTotal(
            unitPrice: $unitPrice,
            quantity: $data->quantity,
            discountType: null,
            discountValue: null,
        );

        return DB::transaction(function () use ($cart, $data, $variant, $unitPrice, $lineTotal) {
            $item = $cart->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product->name.($variant->name ? ' — '.$variant->name : ''),
                'unit_price' => $unitPrice,
                'quantity' => $data->quantity,
                'discount_type' => null,
                'discount_value' => null,
                'line_total' => $lineTotal,
                'notes' => $data->notes,
            ]);

            $this->reserveForItem($cart, $item, $data->quantity, $variant);

            return $item;
        });
    }

    public function updateItem(PosCart $cart, PosCartItem $item, UpdateCartItemData $data): PosCartItem
    {
        $this->assertCartEditable($cart);

        $quantity = $data->quantity ?? $item->quantity;
        $previousQuantity = $item->quantity;

        if ($data->discountProvided) {
            $discountType = $data->discountType;
            $discountValue = $data->discountValue;
        } else {
            $discountType = $item->discount_type;
            $discountValue = $item->discount_value;
        }

        if ($discountValue !== null) {
            $discountValue = (float) $discountValue;
        }

        if ($data->quantity !== null && $data->quantity !== $item->quantity) {
            $variant = ProductVariant::query()->with('product')->find($item->product_variant_id);

            if ($variant !== null && $this->shouldReserve($variant)) {
                $available = $this->availableForLine($cart, $item, $item->product_variant_id);

                if ($data->quantity > $available) {
                    throw ValidationException::withMessages([
                        'quantity' => __('Only :n units available.', ['n' => $available]),
                    ]);
                }
            }
        }

        if ($discountType !== null && $discountValue !== null) {
            $this->validateDiscount(
                discountType: $discountType,
                discountValue: $discountValue,
                unitPrice: (float) $item->unit_price,
                quantity: $quantity,
                approved: $data->approvedByUserId,
            );
        }

        $lineTotal = PosCartItem::computeLineTotal(
            unitPrice: (float) $item->unit_price,
            quantity: $quantity,
            discountType: $discountType,
            discountValue: $discountValue,
        );

        return DB::transaction(function () use (
            $cart,
            $item,
            $quantity,
            $previousQuantity,
            $discountType,
            $discountValue,
            $lineTotal,
            $data,
        ) {
            $item->update([
                'quantity' => $quantity,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'line_total' => $lineTotal,
                'notes' => $data->notes ?? $item->notes,
            ]);

            $item = $item->fresh() ?? $item;

            if ($previousQuantity !== $quantity) {
                $this->syncItemReservation($cart, $item, $previousQuantity, $quantity);
            }

            return $item;
        });
    }

    public function removeItem(PosCart $cart, PosCartItem $item): void
    {
        $this->assertCartEditable($cart);

        DB::transaction(function () use ($cart, $item): void {
            $this->releaseForItem($cart, $item, $this->activeReservationQty($item->id));
            $item->delete();
        });
    }

    /**
     * Remove a line while the cart is in checkout (Completing / Completed + pending sale).
     */
    public function removeCheckoutItem(PosCart $cart, PosCartItem $item): void
    {
        if (! in_array($cart->status, [PosCartStatus::Completing, PosCartStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'status' => __('Cart is not in checkout.'),
            ]);
        }

        DB::transaction(function () use ($cart, $item): void {
            $this->releaseForItem($cart, $item, $this->activeReservationQty($item->id));
            $item->delete();
        });
    }

    public function suspendCart(PosCart $cart): PosCart
    {
        if ($cart->status !== PosCartStatus::Active) {
            throw ValidationException::withMessages([
                'status' => __('Only active carts can be suspended.'),
            ]);
        }

        return $this->carts->update($cart, [
            'status' => PosCartStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function resumeCart(PosCart $cart): PosCart
    {
        if ($cart->status !== PosCartStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => __('Only suspended carts can be resumed.'),
            ]);
        }

        $cart->load('items');

        foreach ($cart->items as $item) {
            $variant = ProductVariant::query()->with('product')->find($item->product_variant_id);

            if ($variant === null || ! $this->shouldReserve($variant)) {
                continue;
            }

            $available = $this->availableForLine($cart, $item, $item->product_variant_id);

            if ($available <= 0 || $item->quantity > $available) {
                $item->update(['stock_warning' => true]);
            }
        }

        return $this->carts->update($cart, [
            'status' => PosCartStatus::Active,
            'suspended_at' => null,
        ]);
    }

    public function voidCart(PosCart $cart): PosCart
    {
        if (! $cart->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('This cart cannot be voided.'),
            ]);
        }

        return DB::transaction(function () use ($cart) {
            $this->releaseAllCartReservations($cart);

            return $this->carts->update($cart, [
                'status' => PosCartStatus::Voided,
                'voided_at' => now(),
            ]);
        });
    }

    public function completeCart(PosCart $cart): PosCart
    {
        if ($cart->status !== PosCartStatus::Completing) {
            throw ValidationException::withMessages([
                'status' => __('Only carts awaiting payment can be dismissed.'),
            ]);
        }

        return $this->carts->update($cart, [
            'status' => PosCartStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function reopenCart(PosCart $cart): PosCart
    {
        if ($cart->status !== PosCartStatus::Completing) {
            throw ValidationException::withMessages([
                'status' => __('Only carts awaiting payment can be reopened.'),
            ]);
        }

        return $this->carts->update($cart, [
            'status' => PosCartStatus::Active,
        ]);
    }

    public function checkoutCart(PosCart $cart): array
    {
        $this->assertCartEditable($cart);

        $cart->load('items');

        $items = $cart->items->map(fn (PosCartItem $item) => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'sku' => $item->sku,
            'name' => $item->name,
            'unit_price' => (float) $item->unit_price,
            'quantity' => $item->quantity,
            'discount_type' => $item->discount_type,
            'discount_value' => $item->discount_value !== null ? (float) $item->discount_value : null,
            'line_total' => $item->resolvedLineTotal(),
        ])->all();

        $subtotal = collect($items)->sum(fn ($i) => $i['unit_price'] * $i['quantity']);
        $grandTotal = collect($items)->sum(fn ($i) => $i['line_total']);
        $totalDiscount = $subtotal - $grandTotal;

        $this->carts->update($cart, [
            'status' => PosCartStatus::Completing,
        ]);

        return [
            'cart_id' => $cart->id,
            'cashier_id' => $cart->cashier_id,
            'branch_id' => $cart->branch_id,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'total_discount' => round($totalDiscount, 2),
            'grand_total' => round($grandTotal, 2),
            'currency' => 'PKR',
            'notes' => $cart->notes,
        ];
    }

    public function validateStockForCart(PosCart $cart): array
    {
        $cart->load('items');
        $warnings = [];

        $variantIds = $cart->items->pluck('product_variant_id')->unique()->filter()->all();
        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        foreach ($cart->items as $item) {
            $variant = $variants->get($item->product_variant_id);

            if ($variant === null || ! $this->shouldReserve($variant)) {
                continue;
            }

            $available = $this->availableForLine($cart, $item, $item->product_variant_id);

            if ($available <= 0) {
                $warnings[$item->id] = [
                    'type' => 'out_of_stock',
                    'available' => 0,
                    'message' => __('Out of stock'),
                ];
            } elseif ($item->quantity > $available) {
                $warnings[$item->id] = [
                    'type' => 'insufficient_stock',
                    'available' => $available,
                    'message' => __('Only :n units available.', ['n' => $available]),
                ];
            }
        }

        return $warnings;
    }

    private function availableStock(int $branchId, int $variantId): int
    {
        return PosBranchWarehouses::totalAvailable($branchId, $variantId);
    }

    private function availableForLine(PosCart $cart, ?PosCartItem $item, int $variantId): int
    {
        $available = $this->availableStock($cart->branch_id, $variantId);

        if ($item === null) {
            return $available;
        }

        return $available + $this->activeReservationQty($item->id);
    }

    private function activeReservationQty(int $cartItemId): int
    {
        return (int) StockReservation::query()
            ->whereNull('released_at')
            ->where('reference_type', self::RESERVATION_REFERENCE_TYPE)
            ->where('reference_id', $cartItemId)
            ->sum('quantity');
    }

    private function reserveForItem(PosCart $cart, PosCartItem $item, int $quantity, ?ProductVariant $variant = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $variant ??= ProductVariant::query()->with('product')->find($item->product_variant_id);

        if ($variant === null || ! $this->shouldReserve($variant)) {
            return;
        }

        $warehouseId = $this->warehouseForReservation($cart, $item, $quantity);

        $this->inventory->reserve(new ReserveStockData(
            warehouseId: $warehouseId,
            variantId: $item->product_variant_id,
            batchId: null,
            quantity: $quantity,
            userId: $cart->cashier_id,
            referenceType: self::RESERVATION_REFERENCE_TYPE,
            referenceId: $item->id,
        ));
    }

    private function releaseForItem(PosCart $cart, PosCartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $variant = ProductVariant::query()->with('product')->find($item->product_variant_id);

        if ($variant === null || ! $this->shouldReserve($variant)) {
            return;
        }

        $remaining = $quantity;

        StockReservation::query()
            ->whereNull('released_at')
            ->where('reference_type', self::RESERVATION_REFERENCE_TYPE)
            ->where('reference_id', $item->id)
            ->orderBy('id')
            ->get()
            ->each(function (StockReservation $reservation) use ($cart, $item, &$remaining): void {
                if ($remaining <= 0) {
                    return;
                }

                $toRelease = min($remaining, (int) $reservation->quantity);

                $this->inventory->release(new ReserveStockData(
                    warehouseId: (int) $reservation->warehouse_id,
                    variantId: $item->product_variant_id,
                    batchId: $reservation->batch_id !== null ? (int) $reservation->batch_id : null,
                    quantity: $toRelease,
                    userId: $cart->cashier_id,
                    referenceType: self::RESERVATION_REFERENCE_TYPE,
                    referenceId: $item->id,
                ));

                $remaining -= $toRelease;
            });
    }

    private function warehouseForReservation(PosCart $cart, PosCartItem $item, int $quantity): int
    {
        $existing = StockReservation::query()
            ->whereNull('released_at')
            ->where('reference_type', self::RESERVATION_REFERENCE_TYPE)
            ->where('reference_id', $item->id)
            ->value('warehouse_id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $resolved = PosBranchWarehouses::resolveForVariant(
            $cart->branch_id,
            $item->product_variant_id,
            max($quantity, (int) $item->quantity),
        );

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->defaultWarehouseForBranch($cart->branch_id);
    }

    private function syncItemReservation(
        PosCart $cart,
        PosCartItem $item,
        int $fromQuantity,
        int $toQuantity,
    ): void {
        if ($fromQuantity === $toQuantity) {
            return;
        }

        if ($toQuantity > $fromQuantity) {
            $variant = ProductVariant::query()->with('product')->find($item->product_variant_id);
            $this->reserveForItem($cart, $item, $toQuantity - $fromQuantity, $variant);

            return;
        }

        $this->releaseForItem($cart, $item, $fromQuantity - $toQuantity);
    }

    private function releaseAllCartReservations(PosCart $cart): void
    {
        $cart->load('items');

        foreach ($cart->items as $item) {
            $this->releaseForItem($cart, $item, $this->activeReservationQty($item->id));
        }
    }

    private function shouldReserve(ProductVariant $variant): bool
    {
        return $variant->product !== null && $variant->product->tracksInventory();
    }

    private function defaultWarehouseForBranch(int $branchId): int
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->first();
        }

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'branch' => __('No active warehouse found for this branch.'),
            ]);
        }

        return $warehouse->id;
    }

    private function resolvePrice(ProductVariant $variant, int $branchId): float
    {
        $branchPrice = $variant->branchPrices
            ->where('branch_id', $branchId)
            ->first();

        return (float) ($branchPrice?->sell_price ?? $variant->sell_price ?? 0);
    }

    private function validateDiscount(
        string $discountType,
        float $discountValue,
        float $unitPrice,
        int $quantity,
        bool $approved,
    ): void {
        $gross = $unitPrice * $quantity;

        if ($discountType === 'flat') {
            if ($discountValue > $gross) {
                throw ValidationException::withMessages([
                    'discount_value' => __('Flat discount cannot exceed the line total.'),
                ]);
            }
        } elseif ($discountType === 'percent') {
            if ($discountValue > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => __('Percentage discount cannot exceed 100%.'),
                ]);
            }

            if ($discountValue > self::DISCOUNT_APPROVAL_THRESHOLD && ! $approved) {
                throw ValidationException::withMessages([
                    'discount_value' => __('Discounts above :threshold% require manager approval.', [
                        'threshold' => self::DISCOUNT_APPROVAL_THRESHOLD,
                    ]),
                ]);
            }
        }
    }

    private function assertCartEditable(PosCart $cart): void
    {
        if (! in_array($cart->status, [PosCartStatus::Active, PosCartStatus::Suspended], true)) {
            throw ValidationException::withMessages([
                'status' => __('Cart is not editable.'),
            ]);
        }
    }
}
