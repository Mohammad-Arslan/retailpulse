<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;

final class StoreExpenseRequest extends FormRequest
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
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'cost_centre_id' => ['nullable', 'integer', 'exists:cost_centres,id'],
            'vendor_party_type' => ['nullable', 'string', 'max:64'],
            'vendor_party_id' => ['nullable', 'integer'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_type_id' => ['nullable', 'integer', 'exists:tax_types,id'],
            'tax_amount' => ['nullable', 'numeric', 'gte:0'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
