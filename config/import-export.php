<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Max Upload Size (KB)
    |--------------------------------------------------------------------------
    | Maximum file size for import uploads in kilobytes.
    */
    'max_upload_size_kb' => (int) env('IMPORT_MAX_UPLOAD_SIZE_KB', 20480),

    /*
    |--------------------------------------------------------------------------
    | Max Row Count
    |--------------------------------------------------------------------------
    | Maximum number of data rows allowed per import file.
    */
    'max_row_count' => (int) env('IMPORT_MAX_ROW_COUNT', 50000),

    /*
    |--------------------------------------------------------------------------
    | Concurrent Import Limit
    |--------------------------------------------------------------------------
    | Maximum active imports (pending/processing) per tenant at any time.
    */
    'max_concurrent_imports_per_tenant' => (int) env('IMPORT_MAX_CONCURRENT_PER_TENANT', 3),

    /*
    |--------------------------------------------------------------------------
    | Upload Rate Limit
    |--------------------------------------------------------------------------
    | Maximum import uploads per minute per user.
    */
    'upload_rate_limit_per_minute' => (int) env('IMPORT_UPLOAD_RATE_LIMIT', 10),
];
