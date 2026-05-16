<?php

declare(strict_types=1);

namespace App\Http\Controllers\ImportExport;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportExport\ConfirmImportRequest;
use App\Http\Requests\ImportExport\SaveMappingRequest;
use App\Http\Requests\ImportExport\SaveRulesRequest;
use App\Http\Requests\ImportExport\UploadImportFileRequest;
use App\Jobs\ValidateImportJob;
use App\Models\ImportExportJob;
use App\Models\ImportValidationProfile;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\SpreadsheetReader;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\RuleMetaRegistry;
use App\Services\ImportExport\Validation\TransformPipeline;
use App\Support\ImportExportAuthorization;
use App\Traits\HandlesImportExportStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ImportWizardController extends Controller
{
    use HandlesImportExportStorage;

    public function upload(UploadImportFileRequest $request): JsonResponse
    {
        $user = $request->user();
        $entityType = $request->validated('entity_type');

        if (! ImportExportAuthorization::canImport($user, $entityType)) {
            abort(Response::HTTP_FORBIDDEN);
        }
        $handler = ImportExportRegistry::importHandler($entityType);
        $path = $this->storeImportFile($request->file('file'), $entityType);
        $preview = SpreadsheetReader::preview($path);
        $tenantId = (int) $user->tenant_id;
        $defaultProfile = ImportValidationProfile::defaultFor($tenantId, $entityType);

        $job = ImportExportJob::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => $entityType,
            'mode' => $request->validated('mode'),
            'input_file_path' => $path,
            'original_filename' => $request->file('file')?->getClientOriginalName(),
            'disk' => app(ImportExportStorageManager::class)->currentDisk(),
            'file_preview' => $preview,
            'step' => 1,
        ]);

        $savedProfiles = ImportValidationProfile::forTenantAndEntity($tenantId, $entityType)
            ->with('columnRules')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ulid' => $job->ulid,
            'headers' => $preview['headers'],
            'preview_rows' => $preview['rows'],
            'system_fields' => $handler->columns(),
            'default_profile' => $defaultProfile,
            'saved_profiles' => $savedProfiles,
            'step' => 1,
        ]);
    }

    public function headers(string $ulid): JsonResponse
    {
        $job = $this->findJob($ulid);
        $this->authorizeImport(request(), $job->entity_type);

        return response()->json($job->file_preview ?? []);
    }

    public function saveMapping(SaveMappingRequest $request, string $ulid): JsonResponse
    {
        $job = $this->findJob($ulid);
        $this->authorizeImport($request, $job->entity_type);
        $job->update([
            'column_mapping' => $request->validated('mapping'),
            'step' => 2,
        ]);

        return response()->json([
            'ulid' => $job->ulid,
            'mapping' => $job->column_mapping,
            'step' => 2,
        ]);
    }

    public function getRules(string $ulid): JsonResponse
    {
        $job = $this->findJob($ulid);
        $this->authorizeImport(request(), $job->entity_type);
        $handler = ImportExportRegistry::importHandler($job->entity_type);
        $tenantId = (int) $job->tenant_id;
        $defaultProfile = ImportValidationProfile::defaultFor($tenantId, $job->entity_type);
        $columnRules = $this->buildColumnRules($handler->columns(), $defaultProfile, $job->column_mapping ?? []);

        $savedProfiles = ImportValidationProfile::forTenantAndEntity($tenantId, $job->entity_type)
            ->with('columnRules')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ulid' => $job->ulid,
            'column_rules' => $columnRules,
            'available_rules' => RuleMetaRegistry::allRuleMeta(),
            'available_transforms' => TransformPipeline::allMeta(),
            'saved_profiles' => $savedProfiles,
            'step' => 3,
        ]);
    }

    public function saveRules(SaveRulesRequest $request, string $ulid): JsonResponse
    {
        $job = $this->findJob($ulid);
        $this->authorizeImport($request, $job->entity_type);
        $columnRules = $request->validated('column_rules');

        $job->update([
            'column_rules_snapshot' => $columnRules,
            'step' => 3,
        ]);

        if ($request->boolean('save_as_profile')) {
            ImportValidationProfile::createFromRules(
                tenantId: (int) $job->tenant_id,
                entityType: $job->entity_type,
                name: (string) $request->validated('profile_name'),
                rules: $columnRules,
                setDefault: $request->boolean('set_as_default'),
                createdBy: (int) $request->user()->id,
            );
        }

        return response()->json([
            'ulid' => $job->ulid,
            'step' => 3,
        ]);
    }

    public function confirm(ConfirmImportRequest $request, string $ulid): JsonResponse
    {
        $job = $this->findJob($ulid);
        $this->authorizeImport($request, $job->entity_type);

        $options = $request->validated('options', []);
        $mapping = $job->column_mapping ?? [];

        if ($mapping === [] && is_array($job->file_preview['headers'] ?? null)) {
            $mapping = $this->guessMapping(
                $job->entity_type,
                $job->file_preview['headers'],
            );
            $job->column_mapping = $mapping;
        }

        $columnRules = $job->column_rules_snapshot;
        if ($columnRules === null || $columnRules === []) {
            $handler = ImportExportRegistry::importHandler($job->entity_type);
            $defaultProfile = ImportValidationProfile::defaultFor((int) $job->tenant_id, $job->entity_type);
            $columnRules = $this->buildColumnRules($handler->columns(), $defaultProfile, $mapping);
        }

        $job->update([
            'is_dry_run' => $request->boolean('is_dry_run'),
            'mode' => $request->validated('mode'),
            'options' => $options,
            'column_mapping' => $mapping,
            'column_rules_snapshot' => $columnRules,
            'step' => 4,
            'queued_at' => now(),
        ]);

        ValidateImportJob::dispatch($job->id);

        return response()->json([
            'ulid' => $job->ulid,
            'channel' => "import-job.{$job->ulid}",
            'step' => 4,
        ]);
    }

    private function findJob(string $ulid): ImportExportJob
    {
        return ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->firstOrFail();
    }

    private function authorizeImport(\Illuminate\Http\Request $request, string $entityType): void
    {
        if (! ImportExportAuthorization::canImport($request->user(), $entityType)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function guessMapping(string $entityType, array $headers): array
    {
        $handler = ImportExportRegistry::importHandler($entityType);
        $mapping = [];

        foreach ($handler->columns() as $column) {
            $key = (string) $column['key'];
            $label = (string) $column['label'];

            if (in_array($key, $headers, true)) {
                $mapping[$key] = $key;

                continue;
            }

            if (in_array($label, $headers, true)) {
                $mapping[$key] = $label;

                continue;
            }

            foreach ($headers as $header) {
                if (strcasecmp($header, $key) === 0 || strcasecmp($header, $label) === 0) {
                    $mapping[$key] = $header;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  list<array<string, mixed>>  $systemColumns
     * @param  array<string, string>  $mapping
     * @return list<array<string, mixed>>
     */
    private function buildColumnRules(array $systemColumns, ?ImportValidationProfile $profile, array $mapping): array
    {
        $profileRules = [];
        if ($profile !== null) {
            foreach ($profile->columnRules as $rule) {
                $profileRules[$rule->column_key] = $rule->toArray();
            }
        }

        $rules = [];

        foreach ($systemColumns as $column) {
            $key = $column['key'];
            $mappedTo = $mapping[$key] ?? $key;
            $existing = $profileRules[$key] ?? null;

            $rules[] = [
                'column_key' => $mappedTo,
                'mapped_to' => $mappedTo,
                'display_label' => $column['label'],
                'rules' => $existing['rules'] ?? $column['default_rules'] ?? [],
                'is_required' => $existing['is_required'] ?? ($column['required'] ?? false),
                'default_value' => $existing['default_value'] ?? null,
                'transform' => $existing['transform'] ?? $column['default_transforms'] ?? [],
            ];
        }

        return $rules;
    }
}
