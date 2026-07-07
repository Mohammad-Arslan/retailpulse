<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-fiscal-years') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3'],
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['required', 'string', 'max:8'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
