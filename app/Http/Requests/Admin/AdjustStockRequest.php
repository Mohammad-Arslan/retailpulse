<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variantId = (int) $this->input('product_variant_id');
            $variant = ProductVariant::query()->with('product')->find($variantId);

            if ($variant?->product?->track_batches && ! $this->filled('batch_id')) {
                $validator->errors()->add(
                    'batch_id',
                    __('Batch is required for batch-tracked products.'),
                );
            }
        });
    }
}
