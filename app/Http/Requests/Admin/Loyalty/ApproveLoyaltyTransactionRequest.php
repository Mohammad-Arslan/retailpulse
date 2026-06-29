<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loyalty;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveLoyaltyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('loyalty.approve') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'size:4'],
        ];
    }
}
