<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-assets') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'unique:asset_categories,code'],
            'default_useful_life_months' => ['required', 'integer', 'min:1'],
            'asset_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'depreciation_expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
