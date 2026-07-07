<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-cheques') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:issued,received'],
            'party_type' => ['required', 'string'],
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cheque_no' => ['required', 'string', 'max:64'],
            'bank' => ['nullable', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }
}
