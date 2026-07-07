<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAccountMappingRequest extends FormRequest
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
            'mapping_key' => ['required', 'string', 'max:64'],
            'account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
