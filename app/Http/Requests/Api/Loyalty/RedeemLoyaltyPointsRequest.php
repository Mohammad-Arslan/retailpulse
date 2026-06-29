<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemLoyaltyPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
        ];
    }
}
