<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\Contracts\ImportHandler;
use InvalidArgumentException;

final class ImportExportRegistry
{
    /** @var array<string, array{import_handler: class-string, export_handler: class-string}> */
    private static array $entities = [];

    /**
     * @param  class-string<ImportHandler>  $importHandlerClass
     * @param  class-string<ExportHandler>  $exportHandlerClass
     */
    public static function register(string $entityType, string $importHandlerClass, string $exportHandlerClass): void
    {
        self::$entities[$entityType] = [
            'import_handler' => $importHandlerClass,
            'export_handler' => $exportHandlerClass,
        ];
    }

    public static function importHandler(string $entityType): ImportHandler
    {
        if (! isset(self::$entities[$entityType]['import_handler'])) {
            throw new InvalidArgumentException("No import handler registered for entity: {$entityType}");
        }

        return app(self::$entities[$entityType]['import_handler']);
    }

    public static function exportHandler(string $entityType): ExportHandler
    {
        if (! isset(self::$entities[$entityType]['export_handler'])) {
            throw new InvalidArgumentException("No export handler registered for entity: {$entityType}");
        }

        return app(self::$entities[$entityType]['export_handler']);
    }

    /**
     * @return list<string>
     */
    public static function allEntities(): array
    {
        return array_keys(self::$entities);
    }
}
