<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * No current profile for the operator yet — import a CV first.
 * Rendered as 404.
 */
final class ProfileNotFoundException extends RuntimeException
{
    public const string CODE = 'PROFILE_NOT_FOUND';

    public function __construct()
    {
        parent::__construct('No matching profile yet. Import a CV first.');
    }
}
