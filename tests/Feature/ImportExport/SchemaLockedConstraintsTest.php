<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Models\ImportExportJob;
use App\Models\User;
use App\Services\ImportExport\Handlers\BrandImportHandler;
use App\Services\ImportExport\Handlers\ProductImportHandler;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Validation\SchemaConstraintDeriver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SchemaLockedConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        SchemaConstraintDeriver::clearCache();

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole('Super Admin');

        $this->other = User::factory()->create(['is_active' => true]);
        $this->other->assignRole('Super Admin');
    }

    public function test_brand_derives_locked_rules_without_schema_identifiers(): void
    {
        $dto = app(SchemaConstraintDeriver::class)->dtoFor(new BrandImportHandler);
        $payload = $dto->toArray();
        $json = json_encode($payload);

        $this->assertDoesNotMatchRegularExpression(
            '/\bbrands\b|\bvarchar\b|constraint|_unique\b|CREATE TABLE/i',
            (string) $json,
        );

        $byField = collect($payload['fields'])->keyBy('field');
        $this->assertTrue($byField->has('code'));
        $this->assertTrue($byField->has('name'));

        $codeTypes = collect($byField['code']['rules'])->pluck('type')->all();
        $this->assertContains('required', $codeTypes);
        $this->assertContains('string', $codeTypes);
        $this->assertContains('unique', $codeTypes);

        $unique = collect($byField['code']['rules'])->firstWhere('type', 'unique');
        $this->assertSame('global', $unique['scope']);
        $this->assertSame('save', $unique['enforced_at']);
        $this->assertTrue($byField['code']['locked']);
    }

    public function test_product_sku_unique_is_advisory_with_enforced_at_save(): void
    {
        $dto = app(SchemaConstraintDeriver::class)->dtoFor(new ProductImportHandler);
        $sku = collect($dto->fields)->firstWhere('field', 'sku');

        $this->assertNotNull($sku);
        $unique = collect($sku['rules'])->firstWhere('type', 'unique');
        $this->assertNotNull($unique);
        $this->assertSame('save', $unique['enforced_at']);
        $this->assertContains($unique['scope'], ['global', 'tenant']);
    }

    public function test_get_rules_includes_locked_constraints_for_owner(): void
    {
        $job = $this->createJob($this->owner);

        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.import-export.imports.rules', $job->ulid));

        $response->assertOk();
        $response->assertJsonPath('ulid', $job->ulid);
        $response->assertJsonStructure([
            'locked_constraints' => [
                'fields' => [
                    ['field', 'locked', 'rules'],
                ],
            ],
            'column_rules',
        ]);

        $json = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/"table"\s*:|"index"\s*:|brands_slug/i', (string) $json);
    }

    public function test_non_owner_cannot_fetch_rules(): void
    {
        $job = $this->createJob($this->owner);

        $this->actingAs($this->other)
            ->getJson(route('admin.import-export.imports.rules', $job->ulid))
            ->assertForbidden();
    }

    public function test_save_rules_reimposes_locked_rules_when_client_strips_them(): void
    {
        $job = $this->createJob($this->owner);

        $response = $this->actingAs($this->owner)->postJson(
            route('admin.import-export.imports.rules.save', $job->ulid),
            [
                'column_rules' => [
                    [
                        'column_key' => 'code',
                        'mapped_to' => 'code',
                        'display_label' => 'Brand Code',
                        'rules' => [['rule' => 'nullable']],
                        'is_required' => false,
                        'transform' => [],
                    ],
                    [
                        'column_key' => 'name',
                        'mapped_to' => 'name',
                        'display_label' => 'Name',
                        'rules' => [],
                        'is_required' => false,
                        'transform' => [],
                    ],
                ],
                'save_as_profile' => false,
            ],
        );

        $response->assertOk();
        $job->refresh();

        $codeRules = collect($job->column_rules_snapshot)
            ->first(fn (array $col): bool => in_array($col['column_key'] ?? '', ['code', 'slug'], true)
                || ($col['system_key'] ?? null) === 'code'
                || ($col['mapped_to'] ?? null) === 'code');

        $this->assertNotNull($codeRules);
        $ruleNames = collect($codeRules['rules'])->map(
            fn (mixed $rule): string => is_string($rule) ? $rule : (string) ($rule['rule'] ?? ''),
        )->all();

        $this->assertContains('required', $ruleNames);
        $this->assertContains('unique_in_db', $ruleNames);
        $this->assertTrue((bool) ($codeRules['is_required'] ?? false));
    }

    public function test_parity_covers_not_null_and_unique_for_mapped_columns(): void
    {
        SchemaConstraintDeriver::clearCache();

        foreach (ImportExportRegistry::allEntities() as $entityType) {
            $handler = ImportExportRegistry::importHandler($entityType);
            $models = $handler->targetModels();
            $map = $handler->columnMap();

            if ($models === [] || $map === []) {
                continue;
            }

            $derived = app(SchemaConstraintDeriver::class)->derive($handler);
            $engineByField = $derived['engine_by_field'];

            foreach ($models as $modelClass) {
                $model = new $modelClass;
                $table = $model->getTable();
                $physicalToLogical = [];

                foreach ($map as $logical => $spec) {
                    if (str_contains($spec, '.')) {
                        [$cls, $col] = explode('.', $spec, 2);
                        if (class_exists($cls) && (new $cls)->getTable() === $table) {
                            $physicalToLogical[$col] = $logical;
                        } elseif ($cls === $table) {
                            $physicalToLogical[$col] = $logical;
                        }
                    } elseif ($modelClass === $models[0]) {
                        $physicalToLogical[$spec] = $logical;
                    }
                }

                foreach (Schema::getColumns($table) as $column) {
                    $name = $column['name'];
                    if (! isset($physicalToLogical[$name])) {
                        continue;
                    }
                    if (in_array($name, ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                        continue;
                    }

                    $logical = $physicalToLogical[$name];
                    $nullable = (bool) $column['nullable'];
                    $default = $column['default'] ?? null;
                    $auto = (bool) ($column['auto_increment'] ?? false);

                    if (! $nullable && $default === null && ! $auto) {
                        $names = collect($engineByField[$logical] ?? [])->pluck('rule')->all();
                        $this->assertContains(
                            'required',
                            $names,
                            "{$entityType}.{$logical} must lock required for NOT NULL {$table}.{$name}",
                        );
                    }
                }

                foreach (Schema::getIndexes($table) as $index) {
                    if (! ($index['unique'] ?? false) || ($index['primary'] ?? false)) {
                        continue;
                    }

                    $cols = array_values(array_filter(
                        $index['columns'],
                        fn (string $c): bool => ! in_array($c, ['id', 'tenant_id'], true),
                    ));

                    if (count($cols) !== 1 || ! isset($physicalToLogical[$cols[0]])) {
                        continue;
                    }

                    $logical = $physicalToLogical[$cols[0]];
                    $names = collect($engineByField[$logical] ?? [])->pluck('rule')->all();
                    $this->assertContains(
                        'unique_in_db',
                        $names,
                        "{$entityType}.{$logical} must lock unique for {$table}.".$cols[0],
                    );
                }
            }
        }
    }

    private function createJob(User $user): ImportExportJob
    {
        return ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'brands',
            'mode' => 'create',
            'status' => 'pending',
            'original_filename' => 'brands.csv',
            'column_mapping' => ['code' => 'code', 'name' => 'name'],
            'file_preview' => ['headers' => ['code', 'name'], 'rows' => []],
        ]);
    }
}
