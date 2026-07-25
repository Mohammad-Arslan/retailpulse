<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.manage-structures') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('legal_entity_id') === '') {
            $this->merge(['legal_entity_id' => null]);
        }

        $components = $this->input('components');

        if (is_array($components)) {
            $this->merge(['components' => array_map(
                static function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }

                    foreach (['amount_or_rate', 'sequence'] as $field) {
                        if (($row[$field] ?? null) === '') {
                            $row[$field] = null;
                        }
                    }

                    return $row;
                },
                $components,
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'components' => ['nullable', 'array'],
            'components.*.pay_component_id' => ['required', 'integer', 'distinct', 'exists:pay_components,id'],
            'components.*.amount_or_rate' => ['nullable', 'numeric', 'min:0'],
            'components.*.sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
