<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\StockMovementReason;
use App\Models\Inventory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryStockChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Inventory $inventory,
        public readonly int $previousOnHand,
        public readonly int $previousReserved,
        public readonly StockMovementReason $reason,
        public readonly ?int $stockMovementId = null,
    ) {}

    public function broadcastWhen(): bool
    {
        $this->inventory->loadMissing('warehouse');

        return $this->inventory->warehouse?->branch_id !== null;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $this->inventory->loadMissing('warehouse', 'variant.product');

        $branchId = $this->inventory->warehouse?->branch_id;

        if ($branchId === null) {
            return [];
        }

        return [new PrivateChannel('branch.'.$branchId)];
    }

    public function broadcastAs(): string
    {
        return 'inventory.stock-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->inventory->loadMissing('warehouse', 'variant.product', 'variant.preferredSupplier');

        $variant = $this->inventory->variant;
        $product = $variant?->product;
        $available = $this->inventory->availableQuantity();
        $reorderPoint = $variant?->reorder_point;
        $isLowStock = $reorderPoint !== null && $available <= $reorderPoint;

        return [
            'inventory_id' => $this->inventory->id,
            'warehouse_id' => $this->inventory->warehouse_id,
            'branch_id' => $this->inventory->warehouse?->branch_id,
            'variant_id' => $this->inventory->product_variant_id,
            'batch_id' => $this->inventory->batch_id,
            'sku' => $variant?->sku,
            'variant_name' => $variant?->displayName(),
            'product_name' => $product?->name,
            'new_qty_on_hand' => $this->inventory->quantity_on_hand,
            'new_qty_reserved' => $this->inventory->quantity_reserved,
            'quantity_on_hand' => $this->inventory->quantity_on_hand,
            'quantity_reserved' => $this->inventory->quantity_reserved,
            'available' => $available,
            'previous_on_hand' => $this->previousOnHand,
            'previous_reserved' => $this->previousReserved,
            'reason' => $this->reason->value,
            'reorder_point' => $reorderPoint,
            'is_low_stock' => $isLowStock,
            'preferred_supplier_id' => $isLowStock ? $variant?->preferred_supplier_id : null,
            'preferred_supplier_code' => $isLowStock ? $variant?->preferredSupplier?->code : null,
            'preferred_supplier_name' => $isLowStock ? $variant?->preferredSupplier?->name : null,
            'at' => now()->toIso8601String(),
        ];
    }
}
