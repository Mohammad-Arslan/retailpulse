<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('customers', 'phone')->ignore($customerId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customerId)],
            'ntn' => ['nullable', 'string', 'max:32'],
            'cnic' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'loyalty_tier_id' => ['nullable', 'integer', 'exists:loyalty_tiers,id'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'preferred_payment_method' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
