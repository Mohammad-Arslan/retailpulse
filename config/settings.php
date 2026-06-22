<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Global setting groups
    |--------------------------------------------------------------------------
    |
    | Each group is edited in Admin → Settings. Users need settings.view to
    | open the area and settings.{group}.update to save that group.
    |
    */

    'groups' => [

        'general' => [
            'label' => 'General',
            'description' => 'Application-wide defaults for currency, timezone, and display.',
            'icon' => 'settings',
            'permission' => 'settings.general.update',
            'fields' => [
                'default_currency' => [
                    'type' => 'select',
                    'label' => 'Default currency',
                    'description' => 'ISO 4217 code pre-selected when creating new branches.',
                    'default' => 'USD',
                    'options' => config('branches.currencies', []),
                    'rules' => ['required', 'string', 'size:3'],
                ],
                'default_timezone' => [
                    'type' => 'select',
                    'label' => 'Default timezone',
                    'description' => 'PHP timezone identifier pre-selected for new branches.',
                    'default' => 'UTC',
                    'options' => config('branches.timezones', []),
                    'rules' => ['required', 'string', 'max:64'],
                ],
                'date_format' => [
                    'type' => 'select',
                    'label' => 'Date format',
                    'default' => 'Y-m-d',
                    'options' => [
                        'Y-m-d' => '2026-05-16 (Y-m-d)',
                        'd/m/Y' => '16/05/2026 (d/m/Y)',
                        'm/d/Y' => '05/16/2026 (m/d/Y)',
                        'd M Y' => '16 May 2026',
                    ],
                    'rules' => ['required', 'string'],
                ],
                'low_stock_threshold' => [
                    'type' => 'integer',
                    'label' => 'Low stock threshold',
                    'description' => 'Default reorder alert quantity when variant has no reorder point.',
                    'default' => 5,
                    'rules' => ['required', 'integer', 'min:0'],
                ],
            ],
        ],

        'company' => [
            'label' => 'Company',
            'description' => 'Legal and contact details shown on documents and reports.',
            'icon' => 'building',
            'permission' => 'settings.company.update',
            'fields' => [
                'legal_name' => [
                    'type' => 'string',
                    'label' => 'Legal name',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'tax_id' => [
                    'type' => 'string',
                    'label' => 'Tax ID / VAT number',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:64'],
                ],
                'address' => [
                    'type' => 'textarea',
                    'label' => 'Address',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'phone' => [
                    'type' => 'string',
                    'label' => 'Phone',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:32'],
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Contact email',
                    'default' => '',
                    'rules' => ['nullable', 'email', 'max:255'],
                ],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Control system alerts and outbound notification channels.',
            'icon' => 'bell',
            'permission' => 'settings.notifications.update',
            'fields' => [
                'email_enabled' => [
                    'type' => 'boolean',
                    'label' => 'Email notifications',
                    'default' => true,
                ],
                'sms_enabled' => [
                    'type' => 'boolean',
                    'label' => 'SMS notifications',
                    'default' => false,
                ],
                'low_stock_alerts' => [
                    'type' => 'boolean',
                    'label' => 'Low stock alerts',
                    'default' => true,
                ],
                'daily_summary_email' => [
                    'type' => 'boolean',
                    'label' => 'Daily summary email',
                    'default' => false,
                ],
            ],
        ],

        'tax' => [
            'label' => 'Tax',
            'description' => 'Configure how sales tax is calculated and displayed across the POS and invoices.',
            'icon' => 'percent',
            'permission' => 'settings.tax.update',
            'fields' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Enable tax calculation',
                    'description' => 'When off, all tax columns are hidden in the POS, checkout, and invoices.',
                    'default' => true,
                ],
                'mode' => [
                    'type' => 'select',
                    'label' => 'Tax mode',
                    'description' => 'Exclusive: tax is added on top of the item price. Inclusive: tax is already included in the price.',
                    'default' => 'exclusive',
                    'options' => [
                        'exclusive' => 'Exclusive (added on top)',
                        'inclusive' => 'Inclusive (included in price)',
                    ],
                    'rules' => ['required', 'in:exclusive,inclusive'],
                ],
                'default_rate' => [
                    'type' => 'string',
                    'label' => 'Default tax rate',
                    'description' => 'Fallback rate as a decimal (e.g. 0.16 for 16%). Overridden by product/category-level rates.',
                    'default' => '0.00',
                    'rules' => ['required', 'numeric', 'min:0', 'max:1'],
                ],
                'per_item' => [
                    'type' => 'boolean',
                    'label' => 'Calculate tax per line item',
                    'description' => 'When off, tax is computed once on the cart total instead of per item.',
                    'default' => true,
                ],
                'rounding' => [
                    'type' => 'select',
                    'label' => 'Tax rounding mode',
                    'default' => 'half_up',
                    'options' => [
                        'half_up' => 'Half-up (standard)',
                        'half_even' => 'Half-even (banker\'s rounding)',
                        'truncate' => 'Truncate',
                    ],
                    'rules' => ['required', 'in:half_up,half_even,truncate'],
                ],
            ],
        ],

        'checkout' => [
            'label' => 'Checkout & Payments',
            'description' => 'Configure payment methods, cash change, layaway, invoice numbering, and inventory deduction timing.',
            'icon' => 'shopping-cart',
            'permission' => 'settings.checkout.update',
            'fields' => [
                'cash_change_enabled' => [
                    'type' => 'boolean',
                    'label' => 'Enable cash change calculation',
                    'description' => 'Shows tendered amount and change due for cash payments.',
                    'default' => true,
                ],
                'cash_change_rounding_mode' => [
                    'type' => 'select',
                    'label' => 'Cash change rounding',
                    'description' => 'How to round the change returned to the customer.',
                    'default' => 'none',
                    'options' => [
                        'none' => 'No rounding',
                        'nearest_5' => 'Round to nearest 5',
                    ],
                    'rules' => ['required', 'in:none,nearest_5'],
                ],
                'split_tender_enabled' => [
                    'type' => 'boolean',
                    'label' => 'Allow split tender',
                    'description' => 'Allow a single sale to be paid with multiple payment methods.',
                    'default' => true,
                ],
                'layaway_enabled' => [
                    'type' => 'boolean',
                    'label' => 'Enable layaway (partial payment)',
                    'description' => 'Allow sales to be confirmed with a deposit, with the balance paid later.',
                    'default' => false,
                ],
                'layaway_min_deposit_percent' => [
                    'type' => 'string',
                    'label' => 'Minimum deposit % for layaway',
                    'description' => 'Minimum percentage of grand total required as deposit. 0 = no minimum.',
                    'default' => '0',
                    'rules' => ['required', 'numeric', 'min:0', 'max:100'],
                ],
                'layaway_max_balance_days' => [
                    'type' => 'integer',
                    'label' => 'Maximum layaway days',
                    'description' => 'Days before an unpaid layaway balance is flagged as overdue.',
                    'default' => 30,
                    'rules' => ['required', 'integer', 'min:1'],
                ],
                'invoice_number_prefix' => [
                    'type' => 'string',
                    'label' => 'Invoice number prefix',
                    'description' => 'Prefix applied to all invoice numbers (e.g. INV).',
                    'default' => 'INV',
                    'rules' => ['required', 'string', 'max:10'],
                ],
                'invoice_number_digits' => [
                    'type' => 'integer',
                    'label' => 'Invoice number digits',
                    'description' => 'Zero-padded width of the sequential portion.',
                    'default' => 8,
                    'rules' => ['required', 'integer', 'min:4', 'max:12'],
                ],
                'default_invoice_template' => [
                    'type' => 'select',
                    'label' => 'Default invoice template',
                    'default' => 'a4',
                    'options' => [
                        'a4' => 'A4 (full invoice)',
                        'thermal_80mm' => '80mm Thermal receipt',
                    ],
                    'rules' => ['required', 'in:a4,thermal_80mm'],
                ],
                'receipt_print_mode' => [
                    'type' => 'select',
                    'label' => 'Receipt print mode',
                    'description' => 'Controls when a receipt is printed after sale completion.',
                    'default' => 'manual',
                    'options' => [
                        'auto' => 'Auto — print immediately on completion',
                        'manual' => 'Manual — show Print button',
                        'off' => 'Off — no printing',
                    ],
                    'rules' => ['required', 'in:auto,manual,off'],
                ],
                'inventory_deduct_on' => [
                    'type' => 'select',
                    'label' => 'Deduct inventory when',
                    'description' => 'Controls when stock is reduced: on full payment completion, or when the first payment is applied.',
                    'default' => 'sale_completed',
                    'options' => [
                        'sale_completed' => 'Sale completed (balance = 0)',
                        'payment_started' => 'First payment applied',
                    ],
                    'rules' => ['required', 'in:sale_completed,payment_started'],
                ],
                'payment_method_cash' => [
                    'type' => 'boolean',
                    'label' => 'Accept cash',
                    'default' => true,
                ],
                'payment_method_card' => [
                    'type' => 'boolean',
                    'label' => 'Accept card',
                    'default' => true,
                ],
                'payment_method_mobile_wallet' => [
                    'type' => 'boolean',
                    'label' => 'Accept mobile wallet',
                    'default' => true,
                ],
                'payment_method_bank_transfer' => [
                    'type' => 'boolean',
                    'label' => 'Accept bank transfer',
                    'default' => true,
                ],
                'payment_method_credit' => [
                    'type' => 'boolean',
                    'label' => 'Accept customer credit',
                    'description' => 'Requires a customer on the sale (Phase 9 enforces credit limits).',
                    'default' => false,
                ],
                'payment_method_wallet' => [
                    'type' => 'boolean',
                    'label' => 'Accept loyalty wallet',
                    'description' => 'Deduct payment from the customer wallet balance.',
                    'default' => false,
                ],
                'payment_method_store_credit' => [
                    'type' => 'boolean',
                    'label' => 'Accept store credit',
                    'description' => 'Deduct payment from the customer store credit balance.',
                    'default' => false,
                ],
                'invoice_share_email' => [
                    'type' => 'boolean',
                    'label' => 'Allow invoice sharing by email',
                    'default' => true,
                ],
                'invoice_share_link' => [
                    'type' => 'boolean',
                    'label' => 'Allow shareable invoice link',
                    'default' => true,
                ],
                'invoice_share_whatsapp' => [
                    'type' => 'boolean',
                    'label' => 'Allow invoice sharing via WhatsApp',
                    'default' => false,
                ],
                'invoice_share_print' => [
                    'type' => 'boolean',
                    'label' => 'Allow invoice print from share API',
                    'default' => true,
                ],
                'whatsapp_api_url' => [
                    'type' => 'string',
                    'label' => 'WhatsApp API URL (optional)',
                    'description' => 'When set, POST invoice link to this endpoint. Leave blank to log only (stub).',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
            ],
        ],

        'fbr' => [
            'label' => 'FBR / IRIS Integration',
            'description' => 'Pakistan Federal Board of Revenue POS integration. All fields are ignored when FBR is disabled.',
            'icon' => 'building-2',
            'permission' => 'settings.fbr.update',
            'fields' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Enable FBR IRIS reporting',
                    'description' => 'When on, every completed sale is reported to the FBR IRIS endpoint.',
                    'default' => false,
                ],
                'pos_id' => [
                    'type' => 'string',
                    'label' => 'FBR POS ID',
                    'description' => 'POS identifier assigned by FBR during registration.',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:64'],
                ],
                'user_id' => [
                    'type' => 'string',
                    'label' => 'FBR portal user ID',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:64'],
                ],
                'password' => [
                    'type' => 'encrypted',
                    'label' => 'FBR API password',
                    'default' => '',
                ],
                'iris_endpoint' => [
                    'type' => 'string',
                    'label' => 'IRIS endpoint URL',
                    'description' => 'Full URL to the FBR IRIS invoice reporting API.',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
                'gst_rate' => [
                    'type' => 'string',
                    'label' => 'GST rate',
                    'description' => 'GST rate applied to all taxable items when FBR is enabled (decimal, e.g. 0.16 for 16%).',
                    'default' => '0.16',
                    'rules' => ['required', 'numeric', 'min:0', 'max:1'],
                ],
                'failure_mode' => [
                    'type' => 'select',
                    'label' => 'FBR failure mode',
                    'description' => 'Queue: sale completes even if IRIS is unreachable; retries in background. Block: sale is held until IRIS confirms.',
                    'default' => 'queue',
                    'options' => [
                        'queue' => 'Queue (recommended — non-blocking)',
                        'block' => 'Block (sale held until IRIS confirms)',
                    ],
                    'rules' => ['required', 'in:queue,block'],
                ],
                'retry_max_attempts' => [
                    'type' => 'integer',
                    'label' => 'Max retry attempts',
                    'description' => 'Number of times to retry a failed IRIS submission before marking it failed.',
                    'default' => 3,
                    'rules' => ['required', 'integer', 'min:1', 'max:10'],
                ],
                'retry_backoff_sec' => [
                    'type' => 'integer',
                    'label' => 'Retry backoff (seconds)',
                    'description' => 'Seconds multiplied by attempt count between retries.',
                    'default' => 60,
                    'rules' => ['required', 'integer', 'min:10'],
                ],
            ],
        ],

        'import_export' => [
            'label' => 'Import / export storage',
            'description' => 'File storage backend for spreadsheet imports, exports, and error reports.',
            'icon' => 'database',
            'permission' => 'settings.import-export.update',
            'test_connection' => true,
            'fields' => [
                'disk' => [
                    'type' => 'select',
                    'label' => 'Storage disk',
                    'default' => 'local',
                    'options' => [
                        'local' => 'Local filesystem',
                        's3' => 'Amazon S3',
                        'minio' => 'MinIO',
                        'sftp' => 'SFTP',
                    ],
                    'rules' => ['required', 'in:local,s3,minio,sftp'],
                ],
                'local_root' => [
                    'type' => 'string',
                    'label' => 'Local root folder',
                    'default' => 'import_exports',
                    'rules' => ['required_if:values.disk,local', 'nullable', 'string', 'max:128'],
                ],
                's3_bucket' => [
                    'type' => 'string',
                    'label' => 'S3 bucket',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                's3_region' => [
                    'type' => 'string',
                    'label' => 'S3 region',
                    'default' => 'us-east-1',
                    'rules' => ['nullable', 'string', 'max:64'],
                ],
                's3_key' => [
                    'type' => 'string',
                    'label' => 'S3 access key',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                's3_secret' => [
                    'type' => 'encrypted',
                    'label' => 'S3 secret key',
                    'default' => '',
                ],
                's3_url' => [
                    'type' => 'string',
                    'label' => 'S3 URL (optional)',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
                'minio_endpoint' => [
                    'type' => 'string',
                    'label' => 'MinIO endpoint',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
                'minio_bucket' => [
                    'type' => 'string',
                    'label' => 'MinIO bucket',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'minio_key' => [
                    'type' => 'string',
                    'label' => 'MinIO access key',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'minio_secret' => [
                    'type' => 'encrypted',
                    'label' => 'MinIO secret key',
                    'default' => '',
                ],
                'minio_use_ssl' => [
                    'type' => 'boolean',
                    'label' => 'MinIO use SSL',
                    'default' => true,
                ],
                'sftp_host' => [
                    'type' => 'string',
                    'label' => 'SFTP host',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'sftp_user' => [
                    'type' => 'string',
                    'label' => 'SFTP username',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:128'],
                ],
                'sftp_pass' => [
                    'type' => 'encrypted',
                    'label' => 'SFTP password',
                    'default' => '',
                ],
                'sftp_key_path' => [
                    'type' => 'string',
                    'label' => 'SFTP private key path',
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
                'sftp_root' => [
                    'type' => 'string',
                    'label' => 'SFTP root path',
                    'default' => '/imports',
                    'rules' => ['nullable', 'string', 'max:512'],
                ],
                'signed_url_ttl' => [
                    'type' => 'integer',
                    'label' => 'Signed URL TTL (minutes)',
                    'default' => 30,
                    'rules' => ['required', 'integer', 'min:1', 'max:1440'],
                ],
                'temp_file_ttl' => [
                    'type' => 'integer',
                    'label' => 'Temp file TTL (minutes)',
                    'default' => 1440,
                    'rules' => ['required', 'integer', 'min:60', 'max:10080'],
                ],
            ],
        ],

        'inventory' => [
            'label' => 'Inventory',
            'description' => 'Reservation TTL and default cycle count variance thresholds.',
            'icon' => 'warehouse',
            'permission' => 'settings.inventory.update',
            'fields' => [
                'reservation_ttl_minutes' => [
                    'type' => 'integer',
                    'label' => 'Cart reservation TTL (minutes)',
                    'description' => 'POS cart holds expire after this many minutes if not checked out.',
                    'default' => (int) config('inventory.reservation_ttl_minutes', 30),
                    'rules' => ['required', 'integer', 'min:1', 'max:1440'],
                ],
                'count_variance_threshold_pct' => [
                    'type' => 'string',
                    'label' => 'Default count variance threshold (%)',
                    'default' => (string) config('inventory.count_variance_threshold_pct', 5),
                    'rules' => ['required', 'numeric', 'min:0'],
                ],
                'count_variance_threshold_value' => [
                    'type' => 'string',
                    'label' => 'Default count variance threshold (value)',
                    'default' => (string) config('inventory.count_variance_threshold_value', 1000),
                    'rules' => ['required', 'numeric', 'min:0'],
                ],
            ],
        ],

        'customers' => [
            'label' => 'Customers & Loyalty',
            'description' => 'Wallet expiry, loyalty points, AR reminders, and tier automation.',
            'icon' => 'users',
            'permission' => 'settings.general.update',
            'fields' => [
                'wallet_expiry_days' => [
                    'type' => 'integer',
                    'label' => 'Wallet expiry (days)',
                    'description' => 'Days until wallet balance expires after top-up. 0 = no expiry.',
                    'default' => 0,
                    'rules' => ['required', 'integer', 'min:0'],
                ],
                'loyalty_points_per_100' => [
                    'type' => 'integer',
                    'label' => 'Loyalty points per 100 spent',
                    'description' => 'Base points earned per 100 currency units on completed sales.',
                    'default' => 1,
                    'rules' => ['required', 'integer', 'min:0'],
                ],
                'loyalty_auto_tier' => [
                    'type' => 'boolean',
                    'label' => 'Auto upgrade loyalty tiers',
                    'description' => 'Automatically assign tiers based on total loyalty points.',
                    'default' => true,
                ],
                'ar_reminder_days' => [
                    'type' => 'json',
                    'label' => 'AR overdue reminder days',
                    'description' => 'Send reminders when balances fall into configured aging buckets (e.g. 7, 30, 60).',
                    'default' => [7, 30, 60],
                    'rules' => ['required', 'json'],
                ],
            ],
        ],

    ],

];
