<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use Illuminate\Database\QueryException;

/**
 * Classifies database exceptions raised during import row processing.
 *
 * SQLSTATE / driver codes treated as row-level (record error, continue):
 * - Integrity (class 23): 23000, 23502 (not-null), 23503 (FK), 23505 (unique)
 * - Transient after retries exhausted: 40001, 40P01, MySQL 1213 (deadlock), 1205 (lock wait)
 *
 * Everything else (unknown column, missing table, connection loss, …) is systemic — rethrow.
 */
final class ImportQueryExceptionClassifier
{
    public const KIND_INTEGRITY = 'integrity';

    public const KIND_TRANSIENT = 'transient';

    public const KIND_SYSTEMIC = 'systemic';

    public static function classify(QueryException $e): string
    {
        $sqlState = self::sqlState($e);
        $driverCode = self::driverCode($e);

        if (self::isIntegrity($sqlState, $driverCode, $e)) {
            return self::KIND_INTEGRITY;
        }

        if (self::matchesTransient($sqlState, $driverCode)) {
            return self::KIND_TRANSIENT;
        }

        return self::KIND_SYSTEMIC;
    }

    public static function isIntegrityViolation(QueryException $e): bool
    {
        return self::classify($e) === self::KIND_INTEGRITY;
    }

    public static function isTransientFailure(QueryException $e): bool
    {
        return self::classify($e) === self::KIND_TRANSIENT;
    }

    private static function isIntegrity(string $sqlState, ?int $driverCode, QueryException $e): bool
    {
        if (str_starts_with($sqlState, '23')) {
            return true;
        }

        // MySQL / MariaDB often surface integrity as HY000 with specific errno.
        if (in_array($driverCode, [1062, 1048, 1364, 1451, 1452], true)) {
            return true;
        }

        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'unique constraint failed')
            || str_contains($message, 'not null constraint failed')
            || str_contains($message, 'foreign key constraint failed')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'cannot be null')
            || str_contains($message, 'integrity constraint violation');
    }

    private static function matchesTransient(string $sqlState, ?int $driverCode): bool
    {
        if (in_array($sqlState, ['40001', '40P01'], true)) {
            return true;
        }

        // MySQL: 1213 deadlock, 1205 lock wait timeout
        return in_array($driverCode, [1213, 1205], true);
    }

    private static function sqlState(QueryException $e): string
    {
        $code = (string) $e->getCode();

        if (preg_match('/^\d{5}$/', $code) === 1) {
            return $code;
        }

        $errorInfo = $e->errorInfo ?? [];

        if (isset($errorInfo[0]) && is_string($errorInfo[0]) && preg_match('/^\d{5}$/', $errorInfo[0]) === 1) {
            return $errorInfo[0];
        }

        if (preg_match('/SQLSTATE\[(\d{5})]/', $e->getMessage(), $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private static function driverCode(QueryException $e): ?int
    {
        $errorInfo = $e->errorInfo ?? [];

        if (isset($errorInfo[1]) && is_numeric($errorInfo[1])) {
            return (int) $errorInfo[1];
        }

        return null;
    }
}
