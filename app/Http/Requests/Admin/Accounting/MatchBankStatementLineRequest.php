<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class MatchBankStatementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-bank-accounts') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'journal_transaction_id' => ['required', 'integer', 'exists:journal_transactions,id'],
            'matched_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
