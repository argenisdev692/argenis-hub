<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

/**
 * Cloud storage contract (Cloudflare R2 by default).
 *
 * Domain-pure: only native types. The R2StorageAdapter implements it over the
 * S3-compatible `r2` disk. Public access to private objects is ALWAYS via a
 * signed temporary URL — never a permanent public URL (OWASP / BACKEND-PHP §5).
 */
interface StoragePort
{
    /**
     * Persist raw contents and return the stored path.
     */
    public function put(string $path, string $contents, string $visibility = 'private'): string;

    /**
     * Persist an uploaded/local file into $directory and return the stored path.
     */
    public function putFile(string $directory, \SplFileInfo $file, string $visibility = 'private'): string;

    /**
     * Time-limited signed URL for a private object.
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt): string;

    /**
     * Time-limited signed PUT URL for direct browser → cloud uploads (S3/R2).
     *
     * @return array{upload_url: string, headers: array<string, string>}
     */
    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiresAt): array;

    /**
     * Permanent public URL for a PUBLIC object (e.g. brand logos rendered in
     * emails, which cannot use expiring signed URLs). Only valid for objects
     * stored with `public` visibility — never for private user uploads.
     */
    public function publicUrl(string $path): string;

    /**
     * Stream a private object to a local filesystem path (server-side only).
     * Prefer this over unsigned public URLs when reading private R2 objects.
     */
    public function copyToLocal(string $path, string $localPath): void;

    /**
     * Stream a local file into cloud storage without loading it into PHP memory.
     */
    public function putFromPath(string $path, string $localPath, string $visibility = 'private'): string;

    public function delete(string $path): bool;

    public function exists(string $path): bool;
}
