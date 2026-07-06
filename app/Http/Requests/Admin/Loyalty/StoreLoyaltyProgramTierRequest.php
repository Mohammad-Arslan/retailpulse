<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyTierQualificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyProgramTierRequest extends FormRequest
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
            'tier_level' => ['required', 'integer', 'min:1', 'max:100'],
            'qualification_type' => ['required', Rule::in(LoyaltyTierQualificationType::values())],
            'qualification_value' => ['required', 'numeric', 'min:0'],
            'multiplier' => ['required', 'numeric', 'min:0'],
            'benefits_json' => ['nullable', 'array'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
