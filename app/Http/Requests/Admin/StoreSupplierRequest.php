<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'tax_registration_no' => ['nullable', 'string', 'max:64'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'credit_terms_days' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email'],
            'contacts.*.phone' => ['nullable', 'string', 'max:32'],
            'contacts.*.role' => ['nullable', 'string', 'max:64'],
            'contacts.*.is_primary' => ['boolean'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.label' => ['nullable', 'string', 'max:64'],
            'addresses.*.address_line_1' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:128'],
            'addresses.*.state' => ['nullable', 'string', 'max:128'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:32'],
            'addresses.*.country_code' => ['nullable', 'string', 'max:2'],
            'addresses.*.is_default' => ['boolean'],
        ];
    }
}
