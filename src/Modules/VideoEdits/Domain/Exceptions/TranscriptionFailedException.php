<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use RuntimeException;

/**
 * Transcription did not produce a usable transcript.
 *
 * Deliberately NOT a {@see PermanentVideoEditFailure}: a rate limit, a timeout
 * or a 5xx from the provider is exactly what the job's two retries exist for.
 * The one permanent case — audio too large for the provider — is raised as
 * {@see InvalidMediaException} instead, because retrying cannot shrink it.
 */
final class TranscriptionFailedException extends RuntimeException
{
    public static function providerRejected(int $statusCode): self
    {
        return new self("The transcription provider returned HTTP {$statusCode}.");
    }

    public static function unusableResponse(string $detail): self
    {
        return new self("The transcription response was unusable: {$detail}.");
    }
}
