<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRecurringExpenseScheduleRequest extends FormRequest
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
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'cost_centre_id' => ['nullable', 'integer', 'exists:cost_centres,id'],
            'currency_code' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_type_id' => ['nullable', 'integer', 'exists:tax_types,id'],
            'frequency' => ['required', 'string', Rule::in([
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'annual',
                'custom_interval',
            ])],
            'interval_count' => ['required', 'integer', 'min:1'],
            'day_of_period' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'proration_policy' => ['required', 'string', Rule::in(['none', 'first_period', 'last_period', 'both'])],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', Rule::in(['active', 'paused', 'cancelled'])],
        ];
    }
}
