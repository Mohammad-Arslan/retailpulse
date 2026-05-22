<?php

declare(strict_types=1);

namespace App\Http\Requests\ImportExport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmImportRequest extends FormRequest
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
            'is_dry_run' => ['sometimes', 'boolean'],
            'mode' => ['required', 'string', Rule::in(['create', 'update', 'upsert', 'delete'])],
            'options' => ['sometimes', 'array'],
        ];
    }
}
