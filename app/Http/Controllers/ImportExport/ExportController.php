<?php

declare(strict_types=1);

namespace App\Http\Controllers\ImportExport;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExportJob;
use App\Models\ImportExportJob;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ExportController extends Controller
{
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(ImportExportRegistry::allEntities())],
            'options' => ['sometimes', 'array'],
        ]);

        ImportExportRegistry::exportHandler($validated['entity_type']);

        $user = $request->user();
        $job = ImportExportJob::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'user_id' => $user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'export',
            'entity_type' => $validated['entity_type'],
            'options' => $validated['options'] ?? [],
            'disk' => app(ImportExportStorageManager::class)->currentDisk(),
            'queued_at' => now(),
        ]);

        ProcessExportJob::dispatch($job->id);

        return response()->json([
            'ulid' => $job->ulid,
            'channel' => "import-job.{$job->ulid}",
        ]);
    }

    public function download(string $ulid): RedirectResponse
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->where('type', 'export')
            ->firstOrFail();

        $url = app(ImportExportStorageManager::class)
            ->temporaryUrl((string) $job->output_file_path);

        return redirect()->away($url);
    }

    public function errors(string $ulid): RedirectResponse
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->firstOrFail();

        $url = app(ImportExportStorageManager::class)
            ->temporaryUrl((string) $job->output_file_path);

        return redirect()->away($url);
    }
}
