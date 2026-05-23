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

];
