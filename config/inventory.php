<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Cart holds that are neither deducted nor released within this window
    | are auto-released by inventory:release-expired-reservations.
    |
    */

    'reservation_ttl_minutes' => (int) env('INVENTORY_RESERVATION_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Cycle count variance thresholds (defaults)
    |--------------------------------------------------------------------------
    */

    'count_variance_threshold_pct' => (float) env('INVENTORY_COUNT_VARIANCE_PCT', 5),

    'count_variance_threshold_value' => (float) env('INVENTORY_COUNT_VARIANCE_VALUE', 1000),

];
