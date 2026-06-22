<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierInvoiceRequest extends FormRequest
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
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['nullable', 'integer'],
            'lines.*.grn_item_id' => ['nullable', 'integer'],
            'lines.*.product_variant_id' => ['required', 'integer'],
            'lines.*.qty_invoiced' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_total' => ['required', 'numeric'],
            'lines.*.functional_line_total' => ['required', 'numeric'],
        ];
    }
}
