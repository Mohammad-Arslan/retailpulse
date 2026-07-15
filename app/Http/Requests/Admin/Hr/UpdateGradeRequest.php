<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.manage-org') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['legal_entity_id', 'currency_code'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'rank' => ['nullable', 'integer', 'min:0'],
            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where('status', 'active'),
            ],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'mid_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'enforce_salary_band' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
