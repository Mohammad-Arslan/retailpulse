<?php

declare(strict_types=1);

return [
    'core' => ['always_enabled' => true],
    'ar_ap' => ['requires' => ['core']],
    'tax' => ['requires' => ['core']],
    'cost_centres' => ['requires' => ['core']],
    'multi_currency' => ['requires' => ['core']],
    'bank_reconciliation' => ['requires' => ['core']],
    'petty_cash' => ['requires' => ['core']],
    'cheques' => ['requires' => ['core']],
    'fixed_assets' => ['requires' => ['core']],
    'intercompany' => ['requires' => ['core', 'multi_currency']],
    'credit_notes' => ['requires' => ['core', 'ar_ap']],
    'debit_notes' => ['requires' => ['core', 'ar_ap']],
];
