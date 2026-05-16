<?php

declare(strict_types=1);

namespace App\Http\Requests\ImportExport;

use App\Services\ImportExport\ImportExportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadImportFileRequest extends FormRequest
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
            'entity_type' => ['required', 'string', Rule::in(ImportExportRegistry::allEntities())],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'mode' => ['required', 'string', Rule::in(['create', 'update', 'upsert'])],
        ];
    }
}
