<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCostLayerRequest extends FormRequest
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
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'batch_no' => ['nullable', 'string'],
            'received_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:10'],
        ];
    }
}
