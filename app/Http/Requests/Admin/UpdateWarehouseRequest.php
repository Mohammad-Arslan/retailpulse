<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && ($this->user()?->can('warehouses.update') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }
}
