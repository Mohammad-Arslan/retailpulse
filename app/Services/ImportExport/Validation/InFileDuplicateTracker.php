<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

use App\Services\ImportExport\ImportContext;

/**
 * Tracks composite/unique keys seen so far within a single import job's validation
 * pass, so a duplicate elsewhere in the same file is caught at validation time
 * instead of only failing against the database at processing time.
 *
 * One instance per {@see ImportContext} (i.e. per job
 * run) — {@see RuleResolverRegistry} and its resolvers are bound as singletons for
 * the life of the queue worker, so per-job state must live here, not on a resolver.
 */
final class InFileDuplicateTracker
{
    /** @var array<string, true> */
    private array $seen = [];

    /**
     * Returns true (and marks the key seen) if this key was already seen earlier
     * in the same file; returns false the first time a key is seen.
     */
    public function isDuplicate(string $key): bool
    {
        if (isset($this->seen[$key])) {
            return true;
        }

        $this->seen[$key] = true;

        return false;
    }
}
