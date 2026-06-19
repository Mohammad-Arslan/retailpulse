<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Branch operational catalog
    |--------------------------------------------------------------------------
    |
    | Currencies and timezones available when creating or editing branches.
    | Defaults for new branches come from Admin → Settings → General
    | (default_currency, default_timezone).
    |
    */

    'currencies' => [
        'USD' => 'US Dollar (USD)',
        'PKR' => 'Pakistani Rupee (PKR)',
        'EUR' => 'Euro (EUR)',
        'GBP' => 'British Pound (GBP)',
        'AED' => 'UAE Dirham (AED)',
        'SAR' => 'Saudi Riyal (SAR)',
        'INR' => 'Indian Rupee (INR)',
        'AUD' => 'Australian Dollar (AUD)',
        'CAD' => 'Canadian Dollar (CAD)',
    ],

    'timezones' => [
        'UTC' => 'UTC',
        'America/New_York' => 'America/New_York (US Eastern)',
        'America/Chicago' => 'America/Chicago (US Central)',
        'America/Denver' => 'America/Denver (US Mountain)',
        'America/Los_Angeles' => 'America/Los_Angeles (US Pacific)',
        'Europe/London' => 'Europe/London',
        'Europe/Paris' => 'Europe/Paris',
        'Asia/Dubai' => 'Asia/Dubai',
        'Asia/Karachi' => 'Asia/Karachi',
        'Asia/Kolkata' => 'Asia/Kolkata',
        'Asia/Singapore' => 'Asia/Singapore',
        'Australia/Sydney' => 'Australia/Sydney',
    ],

];
