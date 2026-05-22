<?php

declare(strict_types=1);

namespace App\Http\Controllers\ImportExport;

use App\Http\Controllers\Controller;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Services\ImportExport\ImportErrorFormatter;
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

    public function latestImport(Request $request, string $entityType): JsonResponse
    {
        $job = ImportExportJob::query()
            ->where('user_id', $request->user()->id)
            ->forCurrentTenant()
            ->where('entity_type', $entityType)
            ->where('type', 'import')
            ->whereNotNull('queued_at')
            ->withCount('rowErrors')
            ->orderByDesc('created_at')
            ->first();

        return response()->json(['job' => $job]);
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

    public function rowErrors(Request $request, string $ulid): JsonResponse
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->firstOrFail();

        $search = trim((string) $request->query('search', ''));
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        $query = ImportRowError::query()
            ->where('job_id', $job->id)
            ->orderBy('row_index');

        $errors = $query->get();
        $formatter = ImportErrorFormatter::forJob($job);

        $rows = [];
        $errorSummary = [];

        foreach ($errors as $error) {
            $rowData = is_array($error->row_data) ? $error->row_data : [];

            foreach ($error->errors as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $friendlyMessage = $formatter->formatMessage(
                        (string) $field,
                        (string) $message,
                        $rowData[$field] ?? null,
                    );
                    $errorSummary[$friendlyMessage] = ($errorSummary[$friendlyMessage] ?? 0) + 1;

                    $value = $rowData[$field] ?? null;
                    if ($value === null && $rowData !== []) {
                        $value = $rowData;
                    }

                    $rows[] = [
                        'row_index' => $error->row_index,
                        'column' => $field === '_row' ? '—' : $formatter->fieldLabel((string) $field),
                        'value' => is_scalar($value) ? (string) $value : json_encode($value),
                        'message' => $friendlyMessage,
                        'severity' => 'error',
                    ];
                }
            }
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => str_contains(mb_strtolower((string) $row['column']), $needle)
                    || str_contains(mb_strtolower((string) $row['message']), $needle)
                    || str_contains(mb_strtolower((string) $row['value']), $needle)
                    || str_contains((string) $row['row_index'], $needle),
            ));
        }

        $total = count($rows);
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($rows, $offset, $perPage);

        arsort($errorSummary);
        $topErrors = array_slice(
            array_map(
                fn (string $message, int $count): array => ['message' => $message, 'count' => $count],
                array_keys($errorSummary),
                array_values($errorSummary),
            ),
            0,
            5,
        );

        return response()->json([
            'rows' => $pageRows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'summary' => [
                'total_errors' => $total,
                'affected_rows' => $errors->count(),
                'top_errors' => $topErrors,
            ],
        ]);
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
