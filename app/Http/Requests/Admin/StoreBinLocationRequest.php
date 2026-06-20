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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'warehouse_zone_id' => ['nullable', 'integer', Rule::exists('warehouse_zones', 'id')->where('warehouse_id', $warehouseId)],
            'zone' => ['nullable', 'string', 'max:32'],
            'aisle' => ['nullable', 'string', 'max:16'],
            'shelf' => ['nullable', 'string', 'max:16'],
            'bin_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('bin_locations', 'bin_code')->where('warehouse_id', $warehouseId),
            ],
            'capacity_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
