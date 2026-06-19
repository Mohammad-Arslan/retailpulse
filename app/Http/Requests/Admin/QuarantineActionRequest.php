<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuarantineActionRequest extends FormRequest
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
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'batch_id' => ['nullable', 'integer', Rule::exists('product_batches', 'id')],
            'bin_location_id' => ['nullable', 'integer', Rule::exists('bin_locations', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
