<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBinLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bins = $this->input('bins');

        if (! is_array($bins)) {
            return;
        }

        $normalized = array_map(function (mixed $row): array {
            $row = is_array($row) ? $row : [];

            foreach (['warehouse_zone_id', 'zone', 'aisle', 'shelf', 'capacity_limit'] as $field) {
                if (($row[$field] ?? '') === '') {
                    $row[$field] = null;
                }
            }

            return $row;
        }, $bins);

        $this->merge(['bins' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'bins' => ['required', 'array', 'min:1'],
            'bins.*.warehouse_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_zones', 'id')->where('warehouse_id', $warehouseId),
            ],
            'bins.*.zone' => ['nullable', 'string', 'max:32'],
            'bins.*.aisle' => ['nullable', 'string', 'max:16'],
            'bins.*.shelf' => ['nullable', 'string', 'max:16'],
            'bins.*.capacity_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bins.required' => __('Add at least one bin.'),
            'bins.min' => __('Add at least one bin.'),
            'bins.*.warehouse_zone_id.exists' => __('Selected zone is invalid for this warehouse.'),
            'bins.*.capacity_limit.min' => __('Capacity must be at least 1.'),
        ];
    }
}
