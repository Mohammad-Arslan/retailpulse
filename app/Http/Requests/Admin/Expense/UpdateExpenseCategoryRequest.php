<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateExpenseCategoryRequest extends FormRequest
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
        /** @var int $categoryId */
        $categoryId = (int) $this->route('expense_category');

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('expense_categories', 'code')->ignore($categoryId)],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:expense_categories,id', Rule::notIn([$categoryId])],
            'account_mapping_key' => ['nullable', 'string', 'max:128'],
            'is_group' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'default_tax_type_id' => ['nullable', 'integer', 'exists:tax_types,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
