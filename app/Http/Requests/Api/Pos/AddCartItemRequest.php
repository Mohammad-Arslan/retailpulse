<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.access') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
