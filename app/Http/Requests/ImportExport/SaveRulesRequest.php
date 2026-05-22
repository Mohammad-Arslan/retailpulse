<?php

declare(strict_types=1);

namespace App\Http\Requests\ImportExport;

use Illuminate\Foundation\Http\FormRequest;

final class SaveRulesRequest extends FormRequest
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
            'column_rules' => ['required', 'array'],
            'save_as_profile' => ['sometimes', 'boolean'],
            'profile_name' => ['required_if:save_as_profile,true', 'nullable', 'string', 'max:128'],
            'set_as_default' => ['sometimes', 'boolean'],
        ];
    }
}
