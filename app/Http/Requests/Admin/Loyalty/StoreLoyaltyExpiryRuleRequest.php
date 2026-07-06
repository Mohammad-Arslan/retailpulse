<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyExpiryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyExpiryRuleRequest extends FormRequest
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
            'expiry_type' => ['required', Rule::in(LoyaltyExpiryType::values())],
            'value' => ['nullable', 'integer', 'min:1'],
            'grace_period_days' => ['required', 'integer', 'min:0'],
        ];
    }
}
