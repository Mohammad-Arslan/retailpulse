<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-assets') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'asset_code' => ['required', 'string', 'max:64', 'unique:fixed_assets,asset_code'],
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'acquisition_date' => ['required', 'date'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'location' => ['nullable', 'string', 'max:120'],
        ];
    }
}
