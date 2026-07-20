<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveEntitlementRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'accrued_days' => ['required', 'numeric', 'min:0'],
            'carried_forward_days' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
