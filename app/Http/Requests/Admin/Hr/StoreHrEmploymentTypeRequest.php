<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHrEmploymentTypeRequest extends FormRequest
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
        $entityId = $this->input('legal_entity_id');

        return [
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('hr_employment_types', 'code')
                    ->where(fn ($q) => $entityId
                        ? $q->where('legal_entity_id', (int) $entityId)
                        : $q->whereNull('legal_entity_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('legal_entity_id') === '' || $this->input('legal_entity_id') === 'global') {
            $this->merge(['legal_entity_id' => null]);
        }

        if ($this->has('code')) {
            $this->merge(['code' => strtolower(trim((string) $this->input('code')))]);
        }
    }
}
