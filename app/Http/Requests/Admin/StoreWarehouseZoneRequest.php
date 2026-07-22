<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWarehouseZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $zones = $this->input('zones');

        if (! is_array($zones)) {
            return;
        }

        $normalized = array_map(function (mixed $row): array {
            $row = is_array($row) ? $row : [];

            if (($row['capacity_limit'] ?? '') === '') {
                $row['capacity_limit'] = null;
            }

            return $row;
        }, $zones);

        $this->merge(['zones' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zones' => ['required', 'array', 'min:1'],
            'zones.*.name' => ['required', 'string', 'max:255'],
            'zones.*.capacity_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zones.required' => __('Add at least one zone.'),
            'zones.min' => __('Add at least one zone.'),
            'zones.*.name.required' => __('Zone name is required.'),
            'zones.*.capacity_limit.min' => __('Capacity must be at least 1.'),
        ];
    }
}
