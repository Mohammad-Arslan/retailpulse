<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

final class AdjustLoyaltyPointsRequest extends FormRequest
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
            'program_id' => ['required', 'integer', 'exists:loyalty_programs,id'],
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
