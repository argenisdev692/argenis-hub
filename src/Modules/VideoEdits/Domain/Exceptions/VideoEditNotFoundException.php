<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * No edit with that identifier belongs to the caller (HTTP 404). Another user's
 * edit is reported exactly like a missing one (OWASP §11).
 */
final class VideoEditNotFoundException extends DomainException
{
    public static function forUuid(string $uuid): self
    {
        return new self("Video edit [{$uuid}] was not found.");
    }
}
