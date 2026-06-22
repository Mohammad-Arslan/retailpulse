<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderItem */
final class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'description' => $this->description,
            'qty_ordered' => $this->qty_ordered,
            'qty_received' => $this->qty_received,
            'unit_price' => $this->unit_price,
            'tax_rate' => $this->tax_rate,
            'line_total' => $this->line_total,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
            ]),
        ];
    }
}
