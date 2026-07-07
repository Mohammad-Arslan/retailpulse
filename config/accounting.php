<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Event Processing
    |--------------------------------------------------------------------------
    |
    | When an accounting event remains in "processing" longer than this many
    | seconds, it is treated as stale and recovered on the next process attempt.
    |
    */
    'processing_stale_after_seconds' => (int) env('ACCOUNTING_EVENT_STALE_SECONDS', 300),
];
