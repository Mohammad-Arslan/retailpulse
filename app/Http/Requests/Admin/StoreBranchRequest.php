<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\BranchOperationalOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(BranchOperationalOptions::allowedCurrencyCodes())],
            'timezone' => ['required', 'string', Rule::in(BranchOperationalOptions::allowedTimezoneIdentifiers())],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*' => ['array'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.closed' => ['boolean'],
            'weekend_days' => ['nullable', 'array', 'max:7'],
            'weekend_days.*' => ['integer', 'min:0', 'max:6'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'initial_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
        ];
    }
}
