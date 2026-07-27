<?php

declare(strict_types=1);

namespace Tests\Support\ImportExport;

use App\Models\Brand;
use App\Services\ImportExport\Concerns\DeclaresImportSchema;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ExportContext;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * Test-only import handler that always INSERTs (no upsert lookup).
 * Used to exercise DB unique/not-null failures at process time.
 */
final class BlindBrandInsertImportHandler implements ImportHandler
{
    use DeclaresImportSchema;

    /** @var array<int, int> */
    private static array $transientFailRemaining = [];

    private static bool $crashOnceArmed = false;

    public static function reset(): void
    {
        self::$transientFailRemaining = [];
        self::$crashOnceArmed = false;
    }

    public static function failTransientTimes(int $times): void
    {
        self::$transientFailRemaining[0] = $times;
    }

    /**
     * Arms a one-shot, uncaught crash on the next row with code `__crash_once__`.
     * Unlike failTransientTimes (retried inside the same row's isolation loop),
     * this simulates the whole worker process dying mid-job — it throws a plain
     * RuntimeException that escapes ProcessImportJob entirely, leaving whatever
     * rows already committed before this point as the only durable state.
     */
    public static function crashOnce(): void
    {
        self::$crashOnceArmed = true;
    }

    public function targetModels(): array
    {
        return [Brand::class];
    }

    public function columnMap(): array
    {
        return [
            'code' => 'slug',
            'name' => 'name',
        ];
    }

    public function columns(): array
    {
        return [
            [
                'key' => 'code',
                'label' => 'Brand Code',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 128]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'name',
                'label' => 'Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        if (($row['code'] ?? null) === '__crash_once__' && self::$crashOnceArmed) {
            self::$crashOnceArmed = false;

            throw new \RuntimeException('Simulated worker crash mid-job.');
        }

        if ((self::$transientFailRemaining[0] ?? 0) > 0) {
            self::$transientFailRemaining[0]--;

            $previous = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock');
            $previous->errorInfo = ['40001', 1213, $previous->getMessage()];

            throw new QueryException('sqlite', 'insert into brands', [], $previous);
        }

        if (($row['code'] ?? null) === '__systemic__') {
            $previous = new \PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope' in 'field list'");
            $previous->errorInfo = ['42S22', 1054, $previous->getMessage()];

            throw new QueryException('sqlite', 'insert into brands (nope)', [], $previous);
        }

        $name = ($row['code'] ?? null) === '__null_name__'
            ? null
            : ($row['name'] ?? null);

        $brand = Brand::query()->create([
            'tenant_id' => $context->tenantId,
            'slug' => (string) (($row['code'] ?? '') === '__null_name__' ? 'null-name-row' : ($row['code'] ?? '')),
            'name' => $name,
            'is_active' => true,
        ]);

        return ImportRowResult::success($brand->id);
    }

    public function afterImport(ImportContext $context): void
    {
        //
    }

    public function chunkSize(): int
    {
        return 50;
    }
}

final class BlindBrandInsertExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new BlindBrandInsertImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Brand::query()->whereRaw('1 = 0');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        return [];
    }

    public function chunkSize(): int
    {
        return 50;
    }
}
