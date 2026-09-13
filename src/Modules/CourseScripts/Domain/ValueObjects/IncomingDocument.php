<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * An uploaded file as the use-case sees it: a local path plus the metadata the
 * HTTP layer already verified (real MIME, size). Keeps HTTP types out of the
 * Application layer.
 */
final readonly class IncomingDocument
{
    public function __construct(
        public string $localPath,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        /** Assigns a content file to one video by its number (FR-1b). */
        public ?int $videoNumber = null,
    ) {}

    public function checksum(): string
    {
        return (string) hash_file('sha256', $this->localPath);
    }

    /**
     * A storage-safe file name that keeps the author's name readable.
     */
    public function safeFileName(): string
    {
        $extension = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
        $base = pathinfo($this->originalName, PATHINFO_FILENAME)
            |> (static fn (string $name): string => preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'document')
            |> (static fn (string $name): string => trim($name, '._-'))
            |> (static fn (string $name): string => mb_substr($name, 0, 80));

        return ($base === '' ? 'document' : $base).($extension === '' ? '' : '.'.$extension);
    }
}
