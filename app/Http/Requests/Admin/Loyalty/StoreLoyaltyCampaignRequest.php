<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyCampaignStatus;
use App\Enums\LoyaltyCampaignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'campaign_type' => ['required', Rule::in(LoyaltyCampaignType::values())],
            'configuration_json' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(LoyaltyCampaignStatus::values())],
        ];
    }
}
