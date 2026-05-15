<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CheckStockAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:product_batches,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
