<?php

declare(strict_types=1);

namespace App\Http\Requests\ImportExport;

use Illuminate\Foundation\Http\FormRequest;

final class SaveMappingRequest extends FormRequest
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
            'mapping' => ['required', 'array'],
            'mapping.*' => ['required', 'string'],
        ];
    }
}
