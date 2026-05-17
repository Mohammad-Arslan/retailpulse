<?php

declare(strict_types=1);

namespace App\Http\Controllers\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\ImportExportJob;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Traits\HandlesImportExportStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportJobController extends Controller
{
    use HandlesImportExportStorage;

    public function index(Request $request): JsonResponse
    {
        $jobs = ImportExportJob::query()
            ->where('user_id', $request->user()->id)
            ->forCurrentTenant()
            ->withCount('rowErrors')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['jobs' => $jobs]);
    }

    public function show(string $ulid): JsonResponse
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->withCount('rowErrors')
            ->firstOrFail();

        return response()->json(['job' => $job]);
    }

    public function cancel(string $ulid): JsonResponse
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->firstOrFail();

        if (! in_array($job->status, ['pending', 'validating'], true)) {
            return response()->json(['message' => 'Job cannot be cancelled in its current state.'], 422);
        }

        $job->update(['status' => 'cancelled']);

        return response()->json(['job' => $job]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $path = trim(decrypt((string) $request->query('path')));

        if ($path === '' || ! $this->importFileExists($path)) {
            abort(404);
        }

        return $this->storageManager()->download($path);
    }
}
