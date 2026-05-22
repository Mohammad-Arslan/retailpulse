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
                    'type' => 'string',
                    'label' => 'Default currency',
                    'description' => 'ISO 4217 code used when a branch has no override.',
                    'default' => 'USD',
                    'rules' => ['required', 'string', 'size:3'],
                ],
                'default_timezone' => [
                    'type' => 'string',
                    'label' => 'Default timezone',
                    'description' => 'PHP timezone identifier for new branches.',
                    'default' => 'UTC',
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

    ],

];
