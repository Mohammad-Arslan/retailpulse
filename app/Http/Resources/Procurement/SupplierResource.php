<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
final class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_registration_no' => $this->tax_registration_no,
            'currency_code' => $this->currency_code,
            'balance' => number_format((float) $this->balance, 2, '.', ''),
            'is_active' => $this->is_active,
            'on_time_delivery_rate' => $this->on_time_delivery_rate,
            'quality_rejection_rate' => $this->quality_rejection_rate,
            'contacts' => $this->whenLoaded('contacts'),
            'addresses' => $this->whenLoaded('addresses'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
