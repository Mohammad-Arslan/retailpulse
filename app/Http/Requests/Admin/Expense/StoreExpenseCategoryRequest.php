<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExpenseCategoryRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64', 'unique:expense_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'account_mapping_key' => ['nullable', 'string', 'max:128'],
            'is_group' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'default_tax_type_id' => ['nullable', 'integer', 'exists:tax_types,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
