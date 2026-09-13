<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * Lets the browser PUT files straight to the R2 bucket through the presigned
 * URLs `StoragePort::temporaryUploadUrl()` issues (Video Edits AD-1), so a
 * multi-gigabyte clip never travels through PHP.
 *
 * Only CONNECT is opened, and only for the single origin the S3 SDK signs
 * against: `{bucket}.{endpoint host}` for virtual-hosted URLs, or the endpoint
 * itself for path-style ones. No wildcard over `*.r2.cloudflarestorage.com` —
 * that would permit uploads to any Cloudflare customer's bucket.
 */
final class DirectUploadStoragePreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $origin = self::uploadOrigin();

        if ($origin !== null) {
            $policy->add(Directive::CONNECT, $origin);
        }
    }

    #[\NoDiscard]
    public static function uploadOrigin(): ?string
    {
        $endpoint = config('filesystems.disks.r2.endpoint');
        $bucket = config('filesystems.disks.r2.bucket');

        if (! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = parse_url($endpoint, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        $port = parse_url($endpoint, PHP_URL_PORT);
        $portSuffix = is_int($port) ? ":{$port}" : '';
        $pathStyle = (bool) config('filesystems.disks.r2.use_path_style_endpoint');

        return $pathStyle || ! is_string($bucket) || $bucket === ''
            ? "{$scheme}://{$host}{$portSuffix}"
            : "{$scheme}://{$bucket}.{$host}{$portSuffix}";
    }
}
