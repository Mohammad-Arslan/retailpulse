<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'drop_ship' => ['boolean'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'lines.*.qty_ordered' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.price_override_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
