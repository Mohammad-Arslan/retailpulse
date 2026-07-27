<?php

declare(strict_types=1);

namespace App\Http\Requests\ImportExport;

use App\Models\ImportExportJob;
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
        $maxSizeKb = (int) config('import-export.max_upload_size_kb', 20480);

        return [
            'entity_type' => ['required', 'string', Rule::in(ImportExportRegistry::allEntities())],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', "max:{$maxSizeKb}"],
            'mode' => ['required', 'string', Rule::in(['create', 'update', 'upsert'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $this->checkConcurrentImportLimit($validator);
        });
    }

    private function checkConcurrentImportLimit($validator): void
    {
        $user = $this->user();
        if ($user === null) {
            return;
        }

        $maxConcurrent = (int) config('import-export.max_concurrent_imports_per_tenant', 3);
        $tenantId = $user->tenant_id;

        $activeCount = ImportExportJob::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'import')
            ->whereIn('status', ['pending', 'validating', 'validated', 'processing'])
            ->whereNotNull('queued_at')
            ->count();

        if ($activeCount >= $maxConcurrent) {
            $validator->errors()->add(
                'file',
                __('importExport.concurrentLimitReached', ['max' => $maxConcurrent])
            );
        }
    }
}
