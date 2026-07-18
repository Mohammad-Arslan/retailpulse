<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLeaveEntitlementRequest extends FormRequest
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
            'accrued_days' => ['required', 'numeric', 'min:0'],
            'carried_forward_days' => ['required', 'numeric', 'min:0'],
        ];
    }
}
