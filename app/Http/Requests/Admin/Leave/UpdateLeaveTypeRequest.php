<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('payroll_deduction_component_code') === '') {
            $this->merge(['payroll_deduction_component_code' => null]);
        }

        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        if (! $this->has('is_paid')) {
            $this->merge(['is_paid' => false]);
        }

        if (! $this->has('affects_payroll')) {
            $this->merge(['affects_payroll' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leaveTypeId = $this->route('leave_type')?->id ?? $this->route('leave_type');

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('leave_types', 'code')->ignore($leaveTypeId)],
            'name' => ['required', 'string', 'max:255'],
            'is_paid' => ['boolean'],
            'affects_payroll' => ['boolean'],
            'payroll_deduction_component_code' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
