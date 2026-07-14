<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEmployeeRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'primary_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'hire_date' => ['required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'hourly'])],
            'default_cost_centre_id' => ['nullable', 'integer', 'exists:cost_centres,id'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'bank_details_encrypted' => ['nullable', 'array'],
            'bank_details_encrypted.bank_name' => ['nullable', 'string', 'max:120'],
            'bank_details_encrypted.account_number' => ['nullable', 'string', 'max:64'],
            'bank_details_encrypted.iban' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'inactive', 'terminated'])],
        ];
    }
}
