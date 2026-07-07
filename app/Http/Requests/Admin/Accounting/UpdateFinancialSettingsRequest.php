<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\InventoryValuationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFinancialSettingsRequest extends FormRequest
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
            'functional_currency_code' => ['sometimes', 'required', 'string', 'size:3'],
            'fiscal_year_start_month' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'retained_earnings_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'current_year_earnings_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'opening_balance_equity_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'suspense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'rounding_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'fx_gain_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'fx_loss_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'default_inventory_valuation_method' => ['nullable', Rule::enum(InventoryValuationMethod::class)],
            'allow_negative_inventory' => ['boolean'],
            'allow_manual_journal_posting' => ['boolean'],
            'manual_journal_approval_limit' => ['nullable', 'numeric', 'min:0'],
            'backdated_posting_policy' => ['nullable', 'string', 'in:allow,warn,block'],
            'backdated_entry_approval_required' => ['boolean'],
            'fiscal_year_close_approval_required' => ['boolean'],
            'period_lock_mode' => ['nullable', 'string', 'in:fiscal_year,monthly'],
            'journal_numbering_mode' => ['nullable', 'string', 'in:global,branch,branch_fiscal'],
            'accounting_cutover_date' => ['nullable', 'date'],
        ];
    }
}
