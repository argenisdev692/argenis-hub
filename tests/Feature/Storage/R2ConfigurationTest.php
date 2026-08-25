<?php

declare(strict_types=1);

use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\Storage\R2StorageAdapter;

/**
 * The R2 disk is resolved by name at runtime (`config('filesystems.cloud')`),
 * so a missing disk entry does not fail until the first upload in production.
 * These assertions keep that wiring honest without touching the network.
 */
test('the cloud disk is r2 and the disk itself is configured', function (): void {
    expect(config('filesystems.cloud'))->toBe('r2');

    $disk = config('filesystems.disks.r2');

    expect($disk)->toBeArray()
        ->and($disk['driver'])->toBe('s3')
        ->and($disk['region'])->toBe('auto');
});

test('the signing endpoint and the public url are different hosts', function (): void {
    $disk = config('filesystems.disks.r2');

    // Signing against the public r2.dev host fails, and serving from the S3 API
    // host is not public — collapsing the two is the classic R2 misconfiguration.
    expect($disk['endpoint'])->not->toBe($disk['url']);
});

test('a failed r2 write throws instead of returning false', function (): void {
    expect(config('filesystems.disks.r2.throw'))->toBeTrue();
});

test('the storage port resolves to the r2 adapter', function (): void {
    expect(app(StoragePort::class))->toBeInstanceOf(R2StorageAdapter::class);
});

test('the r2 cors policy never allows a wildcard origin', function (): void {
    $cors = config('filesystems.r2_cors');

    expect($cors)->toBeArray()
        ->and($cors['allowed_methods'])->toBeArray()
        ->and($cors['allowed_headers'])->toBeArray()
        ->and($cors['expose_headers'])->toBeArray()
        ->and($cors['max_age_seconds'])->toBeInt();

    foreach ([...$cors['allowed_origins'], ...$cors['extra_origins']] as $origin) {
        expect($origin)->not->toBe('*');
    }
});
