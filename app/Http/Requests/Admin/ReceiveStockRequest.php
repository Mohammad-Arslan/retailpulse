<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\BinLocation;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.receive') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $warehouseId = (int) $this->input('warehouse_id');

        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'batch_id' => ['nullable', 'integer', 'exists:product_batches,id'],
            'batch_no' => ['nullable', 'string', 'max:64'],
            'expiry_date' => ['nullable', 'date'],
            'quantity' => ['required', 'integer', 'min:1'],
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*' => ['string', 'max:128', 'distinct'],
            'bin_location_id' => [
                'nullable',
                'integer',
                Rule::exists('bin_locations', 'id')->where(
                    fn ($q) => $q->where('warehouse_id', $warehouseId)->where('is_active', true),
                ),
            ],
            'to_quarantine' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variantId = (int) $this->input('product_variant_id');
            $variant = ProductVariant::query()->with('product')->find($variantId);

            if ($variant?->product === null) {
                return;
            }

            $serials = array_values(array_filter(
                array_map('trim', (array) $this->input('serial_numbers', [])),
                static fn (string $value): bool => $value !== '',
            ));

            if ($variant->product->track_serials) {
                if ($serials === []) {
                    $validator->errors()->add(
                        'serial_numbers',
                        __('Enter one serial number per unit received.'),
                    );

                    return;
                }

                $quantity = (int) $this->input('quantity');

                if ($quantity !== count($serials)) {
                    $validator->errors()->add(
                        'quantity',
                        __('Quantity must match the number of serial numbers (:count).', ['count' => count($serials)]),
                    );
                }
            } elseif ($serials !== []) {
                $validator->errors()->add(
                    'serial_numbers',
                    __('Serial numbers are only required for serialized products.'),
                );
            }

            $binId = $this->input('bin_location_id');

            if ($binId !== null && $binId !== '') {
                $bin = BinLocation::query()->find((int) $binId);

                if ($bin !== null && $bin->warehouse_id !== (int) $this->input('warehouse_id')) {
                    $validator->errors()->add(
                        'bin_location_id',
                        __('Bin location must belong to the selected warehouse.'),
                    );
                }
            }
        });
    }
}
