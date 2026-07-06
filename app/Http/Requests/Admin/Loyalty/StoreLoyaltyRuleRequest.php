<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyRuleRequest extends FormRequest
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
            'rule_type' => ['required', Rule::in(LoyaltyRuleType::values())],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'conditions_json' => ['nullable', 'array'],
            'reward_json' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
