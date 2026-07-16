<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLeavePolicyRequest extends FormRequest
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
            'exclude_public_holidays' => ['sometimes', 'boolean'],
        ];
    }
}
