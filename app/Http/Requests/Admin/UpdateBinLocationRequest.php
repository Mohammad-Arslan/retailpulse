<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBinLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['warehouse_zone_id', 'zone', 'aisle', 'shelf', 'capacity_limit'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'warehouse_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_zones', 'id')->where('warehouse_id', $warehouseId),
            ],
            'zone' => ['nullable', 'string', 'max:32'],
            'aisle' => ['nullable', 'string', 'max:16'],
            'shelf' => ['nullable', 'string', 'max:16'],
            'capacity_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_zone_id.exists' => __('Selected zone is invalid for this warehouse.'),
            'capacity_limit.min' => __('Capacity must be at least 1.'),
        ];
    }
}
