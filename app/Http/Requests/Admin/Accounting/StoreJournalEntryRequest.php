<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreJournalEntryRequest extends FormRequest
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
            'journal_date' => ['required', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.currency_code' => ['nullable', 'string', 'size:3'],
            'lines.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'lines.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'lines.*.party_type' => ['nullable', 'string', 'max:128'],
            'lines.*.party_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'lines.*.description' => ['nullable', 'string'],
        ];
    }
}
