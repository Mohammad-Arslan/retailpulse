<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLandedCostRequest extends FormRequest
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
            'charge_type' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'allocation_method' => ['required', Rule::in(['quantity', 'weight', 'value', 'manual'])],
            'description' => ['nullable', 'string', 'max:500'],
            'manual_allocations' => ['array'],
            'manual_allocations.*.grn_item_id' => ['required', 'integer'],
            'manual_allocations.*.allocated_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
