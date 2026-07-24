<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // Root-relative so media works when APP_URL host differs (e.g. Laragon vhost vs localhost).
            'url' => rtrim(env('FILESYSTEM_PUBLIC_URL', '/storage'), '/'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // S3-compatible MinIO (Docker: retailpulse-minio). Static env-driven config, kept only
        // so pre-existing rows with disk='minio' (from before the admin-configurable
        // "File Storage" settings screen) keep resolving. New uploads never write here.
        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', 'retailpulse'),
            'url' => env('MINIO_URL', env('AWS_URL')),
            // Browser-facing base for temporaryUrl() rewrite when endpoint is Docker-internal.
            'temporary_url' => env('MINIO_URL', env('AWS_URL')),
            'endpoint' => env('MINIO_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
