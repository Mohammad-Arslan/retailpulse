<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\ChartOfAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateChartOfAccountRequest extends FormRequest
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
        $accountId = $this->route('chart_of_account')?->id ?? $this->route('chart_of_account');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:32', Rule::unique('chart_of_accounts', 'code')->ignore($accountId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ChartOfAccountType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id', Rule::notIn([(int) $accountId])],
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
