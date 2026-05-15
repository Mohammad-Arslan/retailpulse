<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

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
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:branches,code'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*' => ['array'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.closed' => ['boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'warehouse_name' => ['nullable', 'string', 'max:255'],
            'warehouse_code' => ['nullable', 'string', 'max:32', 'alpha_dash'],
        ];
    }
}
