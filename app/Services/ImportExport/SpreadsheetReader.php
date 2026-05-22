<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Services\ImportExport\Storage\ImportExportStorageManager;
use Generator;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

final class SpreadsheetReader
{
    private function __construct(
        private readonly string $filePath,
        private readonly string $disk,
    ) {}

    public static function for(string $filePath, string $disk = 'import_export'): self
    {
        return new self($filePath, $disk);
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    public static function preview(string $filePath, int $rowCount = 6, string $disk = 'import_export'): array
    {
        $reader = self::for($filePath, $disk);
        $headers = $reader->headers();
        $rows = [];

        foreach ($reader->lazyRows() as $row) {
            $rows[] = $row;
            if (count($rows) >= $rowCount) {
                break;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        foreach ($this->openReader()->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index !== 1) {
                    continue;
                }

                return $this->normalizeHeaderRow($row->toArray());
            }
        }

        return [];
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->lazyRows() as $_row) {
            $count++;
        }

        return $count;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function lazyRows(): Generator
    {
        $headers = null;

        foreach ($this->openReader()->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = $row->toArray();

                if ($index === 1) {
                    $headers = $this->normalizeHeaderRow($cells);

                    continue;
                }

                if ($headers === null) {
                    continue;
                }

                $assoc = $this->mapRow($headers, $cells);

                if ($this->isEmptyRow($assoc)) {
                    continue;
                }

                yield $index - 1 => $assoc;
            }

            break;
        }
    }

    /**
     * @return Generator<int, array<int, array<string, mixed>>>
     */
    public function chunkRows(int $chunkSize): Generator
    {
        /** @var array<int, array<string, mixed>> $chunk */
        $chunk = [];

        foreach ($this->lazyRows() as $index => $row) {
            $chunk[$index] = $row;

            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    private function openReader(): ReaderInterface
    {
        $manager = app(ImportExportStorageManager::class);
        $localPath = $this->resolveLocalPath($manager);

        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'csv' => new CsvReader,
            default => new XlsxReader,
        };

        $reader->open($localPath);

        return $reader;
    }

    private function resolveLocalPath(ImportExportStorageManager $manager): string
    {
        $stream = $manager->readStream($this->filePath);
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        $dest = fopen($tmp, 'wb');

        stream_copy_to_stream($stream, $dest);
        fclose($dest);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $tmp;
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function normalizeHeaderRow(array $cells): array
    {
        return array_map(
            fn ($cell, int $i) => trim((string) ($cell ?? '')) !== '' ? trim((string) $cell) : 'column_'.($i + 1),
            $cells,
            array_keys($cells),
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $cells): array
    {
        $row = [];

        foreach ($headers as $i => $header) {
            $row[$header] = $cells[$i] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
