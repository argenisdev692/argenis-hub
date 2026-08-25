<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Shared\Domain\Ports\StoragePort;

/**
 * StoragePort over the S3-compatible Cloudflare R2 disk.
 *
 * `local`/`public` disks are forbidden as the final destination for user/business
 * uploads (BACKEND-PHP §5). Defaults to the `r2` disk; injectable for tests.
 *
 * Columns should store object keys (`portfolios/cover/{uuid}/file.png`). Legacy
 * rows may hold absolute public URLs — {@see publicUrl()} returns those as-is
 * (never double-prefix with `R2_URL`), while exists/delete/temporaryUrl strip
 * the URL down to the object key for the S3 API.
 */
final readonly class R2StorageAdapter implements StoragePort
{
    private Filesystem $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = Storage::disk($disk ?? (string) config('filesystems.cloud', 'r2'));
    }

    public function put(string $path, string $contents, string $visibility = 'private'): string
    {
        $key = $this->normalizeObjectKey($path);
        $this->disk->put($key, $contents, ['visibility' => $visibility]);

        return $key;
    }

    public function putFile(string $directory, \SplFileInfo $file, string $visibility = 'private'): string
    {
        $path = $this->disk->putFile($directory, $file, ['visibility' => $visibility]);

        if ($path === false) {
            throw new \RuntimeException("Failed to store file in [{$directory}].");
        }

        return $path;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt): string
    {
        return $this->disk->temporaryUrl(
            $this->normalizeObjectKey($path),
            CarbonImmutable::instance($expiresAt),
        );
    }

    /**
     * @return array{upload_url: string, headers: array<string, string>}
     */
    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiresAt): array
    {
        /** @var array{url: string, headers: array<string, string>} $payload */
        $payload = $this->disk->temporaryUploadUrl(
            $this->normalizeObjectKey($path),
            CarbonImmutable::instance($expiresAt),
        );

        return [
            'upload_url' => $payload['url'],
            'headers' => $this->browserSafeUploadHeaders($payload['headers'] ?? []),
        ];
    }

    /**
     * Flysystem/S3 includes hop-by-hop headers (Host, Content-Length, …) that
     * browsers refuse to set on XHR. Only forward headers the browser may send
     * for a direct PUT to a presigned R2 URL.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function browserSafeUploadHeaders(array $headers): array
    {
        $safe = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);

            if ($lower === 'content-type' || str_starts_with($lower, 'x-amz-')) {
                $safe[(string) $name] = (string) $value;
            }
        }

        return $safe;
    }

    public function publicUrl(string $path): string
    {
        // Already a permanent URL (legacy rows / manual inserts) — never
        // concatenate disk `url` again or we produce
        // `{R2_URL}/{https://other-host/...}`.
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        return $this->disk->url($this->normalizeObjectKey($path));
    }

    public function copyToLocal(string $path, string $localPath): void
    {
        $key = $this->normalizeObjectKey($path);
        $stream = $this->disk->readStream($key);
        if ($stream === null) {
            throw new \RuntimeException("Failed to open storage object [{$key}].");
        }

        try {
            $directory = dirname($localPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException("Failed to create local directory [{$directory}].");
            }

            $out = fopen($localPath, 'wb');
            if ($out === false) {
                throw new \RuntimeException("Failed to open local path [{$localPath}].");
            }

            try {
                $copied = stream_copy_to_stream($stream, $out);
                if ($copied === false) {
                    throw new \RuntimeException("Failed to copy storage object [{$key}] to local disk.");
                }
            } finally {
                fclose($out);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function putFromPath(string $path, string $localPath, string $visibility = 'private'): string
    {
        if (! is_file($localPath)) {
            throw new \RuntimeException("Local file not found [{$localPath}].");
        }

        $key = $this->normalizeObjectKey($path);
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("Failed to open local file [{$localPath}].");
        }

        try {
            $ok = $this->disk->put($key, $stream, ['visibility' => $visibility]);
            if ($ok === false) {
                throw new \RuntimeException("Failed to stream [{$localPath}] to storage [{$key}].");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $key;
    }

    public function delete(string $path): bool
    {
        return $this->disk->delete($this->normalizeObjectKey($path));
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($this->normalizeObjectKey($path));
    }

    private function isAbsoluteUrl(string $path): bool
    {
        return str_starts_with($path, 'https://') || str_starts_with($path, 'http://');
    }

    /**
     * Map a stored value to the R2 object key. Absolute public URLs are reduced
     * to their path; keys are returned trimmed.
     */
    private function normalizeObjectKey(string $path): string
    {
        if ($this->isAbsoluteUrl($path)) {
            $parsedPath = (string) parse_url($path, PHP_URL_PATH);

            return ltrim(rawurldecode($parsedPath), '/');
        }

        return ltrim($path, '/');
    }
}
