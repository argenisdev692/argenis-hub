<?php

declare(strict_types=1);

use Shared\Infrastructure\Http\Csp\DirectUploadStoragePreset;
use Spatie\Csp\Policy;

function directUploadPolicy(): string
{
    $policy = new Policy;

    (new DirectUploadStoragePreset)->configure($policy);

    return $policy->getContents();
}

it('allows uploads to the virtual-hosted bucket origin only', function (): void {
    config()->set('filesystems.disks.r2.endpoint', 'https://acct123.r2.cloudflarestorage.com');
    config()->set('filesystems.disks.r2.bucket', 'media');
    config()->set('filesystems.disks.r2.use_path_style_endpoint', false);

    expect(directUploadPolicy())
        ->toContain('connect-src https://media.acct123.r2.cloudflarestorage.com')
        ->not->toContain('*.r2.cloudflarestorage.com');
});

it('allows the endpoint origin for path-style urls', function (): void {
    config()->set('filesystems.disks.r2.endpoint', 'http://localhost:9000/ignored-path');
    config()->set('filesystems.disks.r2.bucket', 'media');
    config()->set('filesystems.disks.r2.use_path_style_endpoint', true);

    expect(directUploadPolicy())->toContain('connect-src http://localhost:9000');
});

it('adds nothing when no endpoint is configured', function (): void {
    config()->set('filesystems.disks.r2.endpoint', null);

    expect(directUploadPolicy())->not->toContain('connect-src');
});

it('is registered in the csp presets', function (): void {
    expect(config('csp.presets'))->toContain(DirectUploadStoragePreset::class);
});
