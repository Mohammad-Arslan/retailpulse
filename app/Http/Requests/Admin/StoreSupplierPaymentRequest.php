<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierPaymentRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:32'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'is_advance' => ['boolean'],
        ];
    }
}
