<?php

declare(strict_types=1);

namespace Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The requested object is not in cloud storage. Distinct from other storage
 * failures so callers can tell "never uploaded" apart from an outage.
 */
final class StorageObjectNotFoundException extends RuntimeException
{
    public static function forPath(string $path, ?Throwable $previous = null): self
    {
        return new self("Storage object [{$path}] does not exist.", previous: $previous);
    }
}
