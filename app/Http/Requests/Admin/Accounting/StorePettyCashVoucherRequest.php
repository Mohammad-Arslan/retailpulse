<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\PettyCashVoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePettyCashVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['expense_account_id', 'date', 'description', 'adjustment_delta'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voucher_type' => ['required', Rule::enum(PettyCashVoucherType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'adjustment_delta' => ['required_if:voucher_type,adjustment', 'nullable', 'numeric'],
        ];
    }
}
