<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== 'variable') {
            $this->merge(['variant_attributes' => null]);
        } else {
            $attributes = $this->input('variant_attributes', []);

            if (is_array($attributes)) {
                $attributes = array_values(array_filter(
                    $attributes,
                    static fn (mixed $attribute): bool => is_array($attribute)
                        && trim((string) ($attribute['name'] ?? '')) !== '',
                ));

                $this->merge([
                    'variant_attributes' => $attributes === [] ? null : $attributes,
                ]);
            }
        }

        $variants = $this->input('variants', []);

        if (is_array($variants)) {
            foreach ($variants as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                if (array_key_exists('reorder_point', $variant) && $variant['reorder_point'] === '') {
                    $variants[$index]['reorder_point'] = null;
                }

                if (array_key_exists('preferred_supplier_id', $variant) && $variant['preferred_supplier_id'] === '') {
                    $variants[$index]['preferred_supplier_id'] = null;
                }
            }

            $this->merge(['variants' => $variants]);
        }

        $this->merge([
            'category_id' => $this->input('category_id') ?: null,
            'brand_id' => $this->input('brand_id') ?: null,
            'unit_id' => $this->input('unit_id') ?: null,
            'default_preferred_supplier_id' => $this->input('default_preferred_supplier_id') ?: null,
            'default_alternate_supplier_ids' => array_values(array_filter(
                array_map('intval', (array) $this->input('default_alternate_supplier_ids', [])),
                static fn (int $id): bool => $id > 0,
            )),
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
            'variants.*.preferred_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'variants.*.alternate_supplier_ids' => ['nullable', 'array'],
            'variants.*.alternate_supplier_ids.*' => ['integer', Rule::exists('suppliers', 'id')],
            'default_preferred_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'default_alternate_supplier_ids' => ['nullable', 'array'],
            'default_alternate_supplier_ids.*' => ['integer', Rule::exists('suppliers', 'id')],
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
