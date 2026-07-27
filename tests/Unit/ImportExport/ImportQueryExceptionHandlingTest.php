<?php

declare(strict_types=1);

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\ImportErrorFormatter;
use App\Services\ImportExport\ImportQueryExceptionClassifier;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

final class ImportQueryExceptionHandlingTest extends TestCase
{
    public function test_classifies_unique_violation_as_integrity(): void
    {
        $e = $this->queryException(
            '23000',
            1062,
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'acme' for key 'brands_slug_unique'",
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_INTEGRITY,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_classifies_not_null_as_integrity(): void
    {
        $e = $this->queryException(
            '23502',
            null,
            'SQLSTATE[23502]: Not null violation: null value in column "name" violates not-null constraint',
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_INTEGRITY,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_classifies_foreign_key_as_integrity(): void
    {
        $e = $this->queryException(
            '23503',
            1452,
            'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails',
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_INTEGRITY,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_classifies_deadlock_as_transient(): void
    {
        $e = $this->queryException(
            '40001',
            1213,
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock',
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_TRANSIENT,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_classifies_lock_wait_as_transient(): void
    {
        $e = $this->queryException(
            'HY000',
            1205,
            'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded',
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_TRANSIENT,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_classifies_unknown_column_as_systemic(): void
    {
        $e = $this->queryException(
            '42S22',
            1054,
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope' in 'field list'",
        );

        $this->assertSame(
            ImportQueryExceptionClassifier::KIND_SYSTEMIC,
            ImportQueryExceptionClassifier::classify($e),
        );
    }

    public function test_formatter_maps_unique_without_leaking_schema_names(): void
    {
        $formatter = new ImportErrorFormatter([
            ['column_key' => 'code', 'mapped_to' => 'slug', 'display_label' => 'Brand Code'],
        ]);

        $errors = $formatter->fromQueryException($this->queryException(
            '23000',
            null,
            'SQLSTATE[23000]: Integrity constraint violation: UNIQUE constraint failed: brands.slug',
        ));

        $this->assertArrayHasKey('code', $errors);
        $message = $errors['code'][0];
        $this->assertStringContainsString('Duplicate value — must be unique', $message);
        $this->assertDoesNotMatchRegularExpression('/\bbrands\b|\bconstraint\b|\bindex\b|_unique\b/i', $message);
    }

    public function test_formatter_maps_not_null_without_leaking_schema_names(): void
    {
        $formatter = new ImportErrorFormatter([
            ['column_key' => 'name', 'display_label' => 'Name'],
        ]);

        $errors = $formatter->fromQueryException($this->queryException(
            '23000',
            null,
            'SQLSTATE[23000]: Integrity constraint violation: NOT NULL constraint failed: brands.name',
        ));

        $this->assertSame(['name' => ['Name: Required value is missing']], $errors);
        $this->assertDoesNotMatchRegularExpression('/\bbrands\b/i', $errors['name'][0]);
    }

    private function queryException(string $sqlState, ?int $driverCode, string $message): QueryException
    {
        $previous = new PDOException($message);
        $previous->errorInfo = [$sqlState, $driverCode, $message];

        return new QueryException('sqlite', 'insert into brands ...', [], $previous);
    }
}
