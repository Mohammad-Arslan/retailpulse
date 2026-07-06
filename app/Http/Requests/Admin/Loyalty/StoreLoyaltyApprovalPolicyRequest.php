<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyApprovalPolicyRequest extends FormRequest
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
            'action_type' => ['required', Rule::in(LoyaltyApprovalActionType::values())],
            'threshold_type' => ['required', Rule::in(LoyaltyApprovalThresholdType::values())],
            'threshold_value' => ['required', 'numeric', 'min:0'],
            'approval_mode' => ['required', Rule::in(LoyaltyApprovalMode::values())],
            'approver_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ];
    }
}
