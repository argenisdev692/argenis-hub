<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Exceptions;

use RuntimeException;

/**
 * A run may not silently inherit another profile's gates (FR-31): unset
 * required gate inputs block the run with the missing input named.
 */
final class ProfileGateIncompleteException extends RuntimeException
{
    /**
     * @param  list<string>  $missingInputs
     */
    public function __construct(array $missingInputs)
    {
        parent::__construct('Profile is missing required gate inputs: '.implode(', ', $missingInputs).'.');
    }
}
