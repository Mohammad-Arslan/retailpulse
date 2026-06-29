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
            'loyalty_points_to_redeem' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $points = (int) $this->input('loyalty_points_to_redeem', 0);
            if ($points > 0 && ! $this->filled('customer_id')) {
                $validator->errors()->add(
                    'customer_id',
                    __('A customer is required to redeem loyalty points.'),
                );
            }
        });
    }
}
