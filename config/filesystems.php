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
    | Cloud Disk
    |--------------------------------------------------------------------------
    |
    | The disk every user/business upload lands on. Resolved by
    | Shared\Infrastructure\Storage\R2StorageAdapter (the StoragePort binding)
    | and by Shared\Infrastructure\Company\CompanyProfile for branding assets.
    | `local`/`public` are never a valid final destination (BACKEND-PHP §5).
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 'r2'),

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
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
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

        /*
         * Cloudflare R2 — S3-compatible, the project's cloud disk.
         *
         * `endpoint` is the S3 API host (signing, put/delete, temporary URLs);
         * `url` is the PUBLIC r2.dev / custom-domain host that public object
         * URLs are built from. They are different hosts on purpose — signing
         * against the public host fails, and serving from the API host is not
         * public. R2 ignores regions, hence the fixed `auto`.
         *
         * `throw` is on: a silent false from a failed upload is far worse than
         * an exception, because the caller then persists a key to nothing.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_PUBLIC_BASE_URL', env('R2_URL')),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | R2 Bucket CORS
    |--------------------------------------------------------------------------
    |
    | Applied to the bucket by `php artisan r2:sync-cors`. The app's own origin
    | (APP_URL) is always included by the command; `extra_origins` covers any
    | additional browser origin that performs direct-to-R2 uploads.
    |
    | Origins are an explicit allowlist — never `*` — because presigned PUT URLs
    | are handed to the browser (OWASP §5).
    |
    */

    'r2_cors' => [
        'allowed_origins' => [],

        'extra_origins' => array_values(array_filter(
            array_map(trim(...), explode(',', (string) env('R2_CORS_EXTRA_ORIGINS', ''))),
        )),

        'allowed_methods' => ['GET', 'PUT', 'HEAD'],

        'allowed_headers' => ['content-type', 'content-length', 'x-amz-*'],

        'expose_headers' => ['etag'],

        'max_age_seconds' => 3600,
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
