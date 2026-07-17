<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.approve') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_start_date' => ['required', 'date'],
            'new_end_date' => ['required', 'date', 'after_or_equal:new_start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
