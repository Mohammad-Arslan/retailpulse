<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TopUpCustomerWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'card', 'bank_transfer'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
