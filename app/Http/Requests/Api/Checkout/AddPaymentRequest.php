<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.access') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['cash', 'card', 'mobile_wallet', 'bank_transfer', 'credit'])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'tendered_amount' => ['nullable', 'numeric', 'min:0.01'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
