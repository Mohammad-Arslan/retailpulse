<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreApprovalDelegationRequest extends FormRequest
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
            'from_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'to_employee_id' => ['required', 'integer', 'exists:employees,id', 'different:from_employee_id'],
            'scope' => ['required', 'string', Rule::in(['all', 'leave', 'expense', 'overtime'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
