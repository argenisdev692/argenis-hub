<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use RuntimeException;

/**
 * The AI provider did not return a usable analysis.
 *
 * Transient by design: a rate limit, timeout or 5xx is what the job's two
 * retries exist for. A refusal to spend the user's content without consent is
 * NOT this — see {@see AiConsentRequiredException}.
 */
final class AiAnalysisFailedException extends RuntimeException
{
    public static function providerFailed(string $detail): self
    {
        return new self("The AI analysis provider failed: {$detail}.");
    }

    public static function unusableResponse(string $detail): self
    {
        return new self("The AI analysis response was unusable: {$detail}.");
    }
}
