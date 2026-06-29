<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyScopeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLoyaltyProgramRequest extends FormRequest
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
            'scope_type' => ['required', Rule::in(LoyaltyProgramScopeType::values())],
            'earn_scope' => ['required', Rule::in(LoyaltyScopeMode::values())],
            'redeem_scope' => ['required', Rule::in(LoyaltyScopeMode::values())],
            'allow_cross_branch_earn' => ['boolean'],
            'allow_cross_branch_redeem' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }
}
