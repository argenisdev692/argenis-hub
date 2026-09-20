<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Exceptions;

use RuntimeException;

/**
 * A Studio record other than a posting or a version is missing — or is not
 * the caller's, which reads the same from outside (BOLA: no existence oracle).
 */
final class RecordNotFoundException extends RuntimeException
{
    #[\NoDiscard]
    public static function of(string $record, string $uuid): self
    {
        return new self(ucfirst($record)." {$uuid} not found.");
    }
}
