<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->input('category_id') ?: null,
            'brand_id' => $this->input('brand_id') ?: null,
            'unit_id' => $this->input('unit_id') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'track_batches' => ['boolean'],
            'is_active' => ['boolean'],
            'regenerate_variants' => ['boolean'],
            'variant_attributes' => ['nullable', 'array'],
            'variant_attributes.*.name' => ['required_with:variant_attributes', 'string', 'max:64'],
            'variant_attributes.*.options' => ['required_with:variant_attributes', 'array', 'min:1'],
            'variant_attributes.*.options.*' => ['string', 'max:64'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.reorder_point' => ['nullable', 'integer', 'min:0'],
            'variants.*.attributes' => ['nullable', 'array'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.child_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'bundle_items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'branch_prices' => ['nullable', 'array'],
            'branch_prices.*.branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'branch_prices.*.sell_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
