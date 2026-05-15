<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StockMovementReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'batch_id' => ['nullable', 'integer', 'exists:product_batches,id'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', Rule::in([
                StockMovementReason::Adjustment->value,
                StockMovementReason::Damaged->value,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
