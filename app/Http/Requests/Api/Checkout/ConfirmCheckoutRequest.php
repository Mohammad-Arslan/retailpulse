<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Checkout;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmCheckoutRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
