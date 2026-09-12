<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * AI edit was requested without explicit consent to send the transcript and
 * script to the external provider (decision R9).
 *
 * Permanent: retrying cannot manufacture consent. The user has to ask again,
 * having agreed.
 */
final class AiConsentRequiredException extends DomainException implements PermanentVideoEditFailure
{
    public const string FAILURE_CODE = 'ai_consent_required';

    public function __construct()
    {
        parent::__construct('AI edit needs your explicit consent to send the transcript and script to the AI provider.');
    }

    public function failureCode(): string
    {
        return self::FAILURE_CODE;
    }

    public function failureDetails(): ?array
    {
        return null;
    }
}
