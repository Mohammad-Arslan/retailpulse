<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-bank-accounts') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'coa_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_title' => ['required', 'string', 'max:120'],
            'account_number_masked' => ['nullable', 'string', 'max:32'],
            'currency_code' => ['required', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
