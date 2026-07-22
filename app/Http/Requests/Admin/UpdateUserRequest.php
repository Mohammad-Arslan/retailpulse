<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branches' => ['nullable', 'array'],
            'branches.*.branch_id' => ['required_with:branches', 'integer', 'exists:branches,id'],
            'branches.*.is_primary' => ['boolean'],
            'pos_pin' => ['nullable', 'string', 'regex:/^\d{6}$/', 'confirmed'],
            'pos_pin_confirmation' => ['nullable', 'string'],
            'clear_pos_pin' => ['boolean'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $userId = $this->route('user')?->id;

        $validator->after(function (Validator $validator) use ($userId): void {
            $employeeId = $this->input('employee_id');
            if ($employeeId === null || $employeeId === '') {
                return;
            }

            $employee = Employee::query()->find($employeeId);
            if ($employee !== null && $employee->user_id !== null && $employee->user_id !== $userId) {
                $validator->errors()->add('employee_id', __('This Employee Is Already Linked To Another User Account.'));
            }
        });
    }
}
