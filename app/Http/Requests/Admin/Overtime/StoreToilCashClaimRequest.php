<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Overtime;

use Illuminate\Foundation\Http\FormRequest;

final class StoreToilCashClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('toil.request-cash-claim') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'hours' => ['required', 'numeric', 'min:0.25'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
