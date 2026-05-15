<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PickingStrategy;
use App\Models\Branch;
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
            'code' => [
                'required',
                'string',
                'max:32',
                'alpha_dash',
                Rule::unique('branches', 'code')->ignore($branch->id),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'picking_strategy' => ['required', Rule::enum(PickingStrategy::class)],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*' => ['array'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.closed' => ['boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'default_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('branch_id', $branch->id),
            ],
        ];
    }
}
