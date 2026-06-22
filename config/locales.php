<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application locales
    |--------------------------------------------------------------------------
    |
    | Codes map to frontend i18n bundles under resources/js/locales/{code}.json.
    | Enable/disable locales per deployment in Admin → Settings → General.
    |
    */

    'default' => 'en',

    'available' => [
        'en' => [
            'label' => 'English',
            'native' => 'English',
            'rtl' => false,
        ],
        'ur' => [
            'label' => 'Urdu',
            'native' => 'اردو',
            'rtl' => true,
        ],
    ],

];
