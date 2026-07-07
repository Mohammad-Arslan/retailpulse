<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StorePettyCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-petty-cash') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'coa_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'register_mode' => ['required', 'string', 'in:imprest,running_balance'],
        ];
    }
}
