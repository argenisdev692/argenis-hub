<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console\Commands;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Console\Command;

final class SyncR2CorsCommand extends Command
{
    protected $signature = 'r2:sync-cors
        {--dry-run : Print the CORS policy without applying it to the bucket}';

    protected $description = 'Apply the R2 bucket CORS policy from config/filesystems.php (APP_URL + R2_CORS_EXTRA_ORIGINS).';

    public function handle(): int
    {
        /** @var array{
         *     allowed_origins: list<string>,
         *     extra_origins: list<string>,
         *     allowed_methods: list<string>,
         *     allowed_headers: list<string>,
         *     expose_headers: list<string>,
         *     max_age_seconds: int
         * } $cors
         */
        $cors = config('filesystems.r2_cors');

        // Primary origin always comes from config('app.url') / APP_URL.
        $origins = array_values(array_unique(array_filter([
            rtrim((string) config('app.url'), '/'),
            ...($cors['extra_origins'] ?? []),
        ])));

        if ($origins === []) {
            $this->error('No CORS origins configured. Set APP_URL (and optionally R2_CORS_EXTRA_ORIGINS).');

            return self::FAILURE;
        }

        $bucket = (string) config('filesystems.disks.r2.bucket');
        if ($bucket === '') {
            $this->error('R2_BUCKET is not configured.');

            return self::FAILURE;
        }

        $rule = [
            'AllowedOrigins' => $origins,
            'AllowedMethods' => $cors['allowed_methods'],
            'AllowedHeaders' => $cors['allowed_headers'],
            'ExposeHeaders' => $cors['expose_headers'],
            'MaxAgeSeconds' => $cors['max_age_seconds'],
        ];

        $this->line('Bucket: '.$bucket);
        $this->line('Origins: '.implode(', ', $origins));
        $this->newLine();
        $this->line(json_encode([$rule], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->option('dry-run')) {
            $this->info('Dry run — CORS policy not applied.');

            return self::SUCCESS;
        }

        $key = (string) config('filesystems.disks.r2.key');
        $secret = (string) config('filesystems.disks.r2.secret');
        $endpoint = (string) config('filesystems.disks.r2.endpoint');

        if ($key === '' || $secret === '' || $endpoint === '') {
            $this->error('R2 credentials or endpoint are not configured.');

            return self::FAILURE;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) config('filesystems.disks.r2.region', 'auto'),
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => (bool) config('filesystems.disks.r2.use_path_style_endpoint', false),
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        try {
            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => [
                    'CORSRules' => [$rule],
                ],
            ]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                $this->error('AccessDenied: R2 API token cannot edit bucket CORS.');
                $this->line('PutBucketCors needs an R2 token with Admin Read & Write (not Object Read & Write).');
                $this->line('Fix options:');
                $this->line('  1) Cloudflare Dashboard → R2 → r2-bucket → Settings → CORS Policy → paste the JSON above');
                $this->line('  2) Create an Admin Read & Write R2 token, set R2_ACCESS_KEY_ID / R2_SECRET_ACCESS_KEY, re-run this command');

                return self::FAILURE;
            }

            throw $e;
        }

        $this->info('R2 bucket CORS policy applied.');

        return self::SUCCESS;
    }
}
