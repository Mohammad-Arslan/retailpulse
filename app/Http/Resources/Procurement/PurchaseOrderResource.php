<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
final class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'branch_id' => $this->branch_id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code,
            ]),
            'currency_code' => $this->currency_code,
            'exchange_rate' => $this->exchange_rate,
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
            'tax_total' => number_format((float) $this->tax_total, 2, '.', ''),
            'total' => number_format((float) $this->total, 2, '.', ''),
            'functional_total' => number_format((float) $this->functional_total, 2, '.', ''),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'drop_ship' => $this->drop_ship,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'grns' => $this->whenLoaded('grns'),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
