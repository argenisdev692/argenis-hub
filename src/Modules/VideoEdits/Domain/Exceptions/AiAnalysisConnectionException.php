<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use RuntimeException;

/**
 * The AI provider could not be reached (DNS, TLS, connect, read timeout).
 *
 * Transient by design: the job's retries exist for these, and the adapter may
 * retry once on a fallback provider before surrendering. Deliberately a
 * sibling of {@see AiAnalysisFailedException} (which is `final`) rather than a
 * subclass, so connection errors and generation errors are two distinct,
 * independently catchable types — the job retries both, but a caller can
 * handle each differently.
 */
final class AiAnalysisConnectionException extends RuntimeException
{
    public static function connectionFailed(string $detail): self
    {
        return new self("The AI analysis connection failed: {$detail}.");
    }
}
