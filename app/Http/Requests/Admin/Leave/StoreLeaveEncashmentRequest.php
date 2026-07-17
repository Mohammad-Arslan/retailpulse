<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveEncashmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.request-encashment') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'days' => ['required', 'numeric', 'min:0.25'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
