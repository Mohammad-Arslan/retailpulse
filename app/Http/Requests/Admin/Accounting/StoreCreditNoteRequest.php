<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.view') || $this->user()?->can('customers.write-off-debt');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'sale_invoice_id' => ['nullable', 'integer', 'exists:sale_invoices,id'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_type_id' => ['nullable', 'integer', 'exists:tax_types,id'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
