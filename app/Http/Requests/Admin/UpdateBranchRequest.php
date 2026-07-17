<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PickingStrategy;
use App\Models\Branch;
use App\Support\BranchOperationalOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            && ($this->user()?->can('update', $branch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(BranchOperationalOptions::allowedCurrencyCodes())],
            'timezone' => ['required', 'string', Rule::in(BranchOperationalOptions::allowedTimezoneIdentifiers())],
            'picking_strategy' => ['required', Rule::enum(PickingStrategy::class)],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*' => ['array'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.closed' => ['boolean'],
            'weekend_days' => ['nullable', 'array', 'max:7'],
            'weekend_days.*' => ['integer', 'min:0', 'max:6'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'default_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('branch_id', $branch->id),
            ],
            'cutover_date' => ['nullable', 'date'],
        ];
    }
}
