<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTaxTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-tax-settings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'unique:tax_types,code'],
            'rate' => ['required', 'numeric', 'min:0'],
            'tax_direction' => ['required', 'string', 'in:sales,purchase,both'],
            'calculation_method' => ['required', 'string', 'in:inclusive,exclusive'],
            'output_tax_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'input_tax_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'tax_payable_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
