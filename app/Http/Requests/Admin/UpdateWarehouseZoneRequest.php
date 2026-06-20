<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWarehouseZoneRequest extends FormRequest
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
        $zoneId = $this->route('zone')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('warehouse_zones', 'code')
                    ->where('warehouse_id', $warehouseId)
                    ->ignore($zoneId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
