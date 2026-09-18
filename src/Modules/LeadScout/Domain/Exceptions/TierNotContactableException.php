<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Drafts exist for Tier A/B leads only (spec FR-40). Rendered as 409.
 */
final class TierNotContactableException extends RuntimeException
{
    public const string CODE = 'TIER_NOT_CONTACTABLE';

    public function __construct(string $reason = 'Drafts are generated for Tier A/B leads only.')
    {
        parent::__construct($reason);
    }
}
