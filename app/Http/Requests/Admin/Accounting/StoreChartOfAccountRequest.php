<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\ChartOfAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreChartOfAccountRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ChartOfAccountType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_group' => ['boolean'],
            'is_postable' => ['boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
