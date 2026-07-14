<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'journal_transaction_id' => ['nullable', 'integer', 'exists:journal_transactions,id'],
            'matched_amount' => ['nullable', 'numeric', 'min:0.01'],
            'transactions' => ['nullable', 'array', 'min:1'],
            'transactions.*.journal_transaction_id' => ['required_with:transactions', 'integer', 'exists:journal_transactions,id'],
            'transactions.*.matched_amount' => ['required_with:transactions', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasSingle = $this->filled('journal_transaction_id');
            $hasMulti = is_array($this->input('transactions')) && count($this->input('transactions')) > 0;

            if ($hasSingle === $hasMulti) {
                $validator->errors()->add(
                    'journal_transaction_id',
                    __('Provide either a single journal transaction or a transactions list, not both.'),
                );
            }
        });
    }
}
