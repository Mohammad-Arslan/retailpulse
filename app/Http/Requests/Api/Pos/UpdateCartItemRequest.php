<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.access') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['nullable', Rule::in(['flat', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'approved' => ['boolean'],
            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
