<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-fiscal-years') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'rate_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'rate_type' => ['nullable', 'string', 'in:spot,average,closing,custom'],
        ];
    }
}
