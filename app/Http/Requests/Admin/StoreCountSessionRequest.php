<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CountScopeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCountSessionRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'scope_type' => ['required', Rule::in(CountScopeType::values())],
            'scope_id' => ['nullable', 'integer'],
            'blind_count' => ['sometimes', 'boolean'],
            'freeze_mode' => ['sometimes', 'boolean'],
            'variance_threshold_pct' => ['nullable', 'numeric', 'min:0'],
            'variance_threshold_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
